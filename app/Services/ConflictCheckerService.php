<?php

namespace App\Services;

use App\Enums\AdminNotificationTypeEnum;
use App\Enums\CalendarEventTypeEnum;
use App\Models\FixObject;
use App\Models\User;
use App\ValueObjects\CalendarConflict;
use App\ValueObjects\CalendarEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Double-Booking Prevention — detects time conflicts per employee across all
 * booking layers (fix, extra, shifts, personal, internal) and enforces the
 * "one employee, one booking" rule.
 *
 * - findConflicts():  compares candidate bookings against existing events
 * - annotate():       read-only marking of conflicts inside a feed (red badges)
 * - assertClean():    persists an AdminNotification + blocks (unless force)
 */
class ConflictCheckerService
{
    /** Layers that occupy an employee's time. External Teamup events excluded. */
    private const BOOKING_LAYERS = ['fix', 'extra', 'shifts', 'personal', 'internal'];

    public function __construct(
        private readonly CalendarEngineService $engine,
        private readonly AdminNotificationService $notifications
    ) {
    }

    // ── Detection ──────────────────────────────────────────────────────────

    /**
     * Compare candidate bookings against existing calendar events.
     *
     * @param  Collection<int, CalendarEvent>  $candidates
     * @param  array<string>  $ignoreEventIds  existing events excluded from the check
     * @return Collection<int, CalendarConflict>
     */
    public function findConflicts(Collection $candidates, array $ignoreEventIds = []): Collection
    {
        if ($candidates->isEmpty()) {
            return collect();
        }

        $from = $candidates->first()->startAt->copy();
        $to   = $candidates->first()->endAt->copy();

        foreach ($candidates as $candidate) {
            if ($candidate->startAt->lt($from)) {
                $from = $candidate->startAt->copy();
            }
            if ($candidate->endAt->gt($to)) {
                $to = $candidate->endAt->copy();
            }
        }

        $existing = $this->engine->forRange($from->copy()->subDay(), $to->copy()->addDay(), self::BOOKING_LAYERS);

        $candidateIds = $candidates->pluck('id')->all();
        $employeeIds  = array_values(array_unique(array_merge(
            $candidates->flatMap(fn(CalendarEvent $e) => $e->employeeIds)->all(),
            $existing->flatMap(fn(CalendarEvent $e) => $e->employeeIds)->all(),
        )));

        $names = $this->namesFor($employeeIds);
        $conflicts = collect();

        foreach ($existing as $existingEvent) {
            if (in_array($existingEvent->id, $ignoreEventIds, true)) {
                continue;
            }

            foreach ($candidates as $candidate) {
                if (! $candidate->overlaps($existingEvent)) {
                    continue;
                }
                if (! $candidate->sharesEmployeeWith($existingEvent)) {
                    continue;
                }

                $sharedEmployees = array_intersect($candidate->employeeIds, $existingEvent->employeeIds);

                foreach ($sharedEmployees as $employeeId) {
                    $conflicts->push($this->makeConflict(
                        (int) $employeeId,
                        $names[(int) $employeeId] ?? "Mitarbeiter #{$employeeId}",
                        $candidate,
                        $existingEvent,
                    ));
                }
            }
        }

        // Candidate ↔ candidate overlap (e.g. one request booking two people+times)
        $candidatesById = $candidates->keyBy('id');
        for ($i = 0; $i < $candidates->count(); $i++) {
            for ($j = $i + 1; $j < $candidates->count(); $j++) {
                $a = $candidates->get($i);
                $b = $candidates->get($j);

                if (! $a->overlaps($b) || ! $a->sharesEmployeeWith($b)) {
                    continue;
                }

                foreach (array_intersect($a->employeeIds, $b->employeeIds) as $employeeId) {
                    $employeeId = (int) $employeeId;
                    $conflicts->push($this->makeConflict(
                        $employeeId,
                        $names[$employeeId] ?? "Mitarbeiter #{$employeeId}",
                        $a,
                        $b,
                    ));
                }
            }
        }

        // Deduplicate (same employee + same event pair)
        return $conflicts
            ->unique(fn(CalendarConflict $c) => $c->employeeId.'|'.min($c->eventAId, $c->eventBId).'|'.max($c->eventAId, $c->eventBId))
            ->values();
    }

    /**
     * Convenience: check a single candidate time window in one call.
     *
     * @param  array<int>  $employeeIds
     * @param  array<string>  $ignoreEventIds
     */
    public function findWindowConflicts(
        array $employeeIds,
        CarbonInterface $start,
        CarbonInterface $end,
        bool $allDay = false,
        array $ignoreEventIds = []
    ): Collection {
        return $this->findConflicts(collect([
            new CalendarEvent(
                id: 'candidate:window',
                type: CalendarEventTypeEnum::InternalEvent,
                layer: 'internal',
                title: 'Neue Buchung',
                startAt: $start,
                endAt: $end,
                allDay: $allDay,
                color: '#000000',
                employeeIds: $employeeIds,
            ),
        ]), $ignoreEventIds);
    }

    /**
     * Candidate events for every schedule of a Fixobjekt within a window —
     * used for contract-level assignment/reassignment.
     *
     * @param  array<int>  $employeeIds
     */
    public function fixCandidates(FixObject $fixObject, CarbonInterface|string $from, ?string $until, array $employeeIds): Collection
    {
        $startDate = Carbon::parse($from);
        $endDate   = $until
            ? Carbon::parse($until)
            : $startDate->copy()->addDays(90);

        if ($fixObject->valid_until && $fixObject->valid_until->lt($endDate)) {
            $endDate = $fixObject->valid_until->copy();
        }
        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy();
        }

        $schedules = $fixObject->schedules()
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->get();

        return $schedules->map(function ($schedule) use ($fixObject, $employeeIds) {
            $dateStr = $schedule->scheduled_date->toDateString();

            if (! $schedule->scheduled_start || ! $schedule->scheduled_end) {
                $start = Carbon::parse($dateStr)->startOfDay();
                $end   = Carbon::parse($dateStr)->endOfDay();
                $allDay = true;
            } else {
                $start  = Carbon::parse($dateStr.' '.$schedule->scheduled_start);
                $end    = Carbon::parse($dateStr.' '.$schedule->scheduled_end);
                $allDay = false;
            }

            return new CalendarEvent(
                id: 'fix_schedule:'.$schedule->id,
                type: CalendarEventTypeEnum::FixSchedule,
                layer: 'jobs',
                title: $fixObject->title,
                startAt: $start,
                endAt: $end,
                allDay: $allDay,
                color: $fixObject->calendar_color ?: '#FFD700',
                employeeIds: $employeeIds,
                sourceId: $schedule->id,
            );
        });
    }

    // ── Enforcement ────────────────────────────────────────────────────────

    /**
     * Persist an admin conflict warning, then block the booking unless forced.
     *
     * @param  Collection<int, CalendarConflict>  $conflicts
     */
    public function assertClean(Collection $conflicts, bool $force = false): void
    {
        if ($conflicts->isEmpty()) {
            return;
        }

        $messages = $conflicts->map(fn(CalendarConflict $c) => $c->message());

        $this->notifications->notify(
            type: AdminNotificationTypeEnum::Conflict,
            title: 'Doppelte Buchung erkannt',
            message: $messages->implode("\n"),
            payload: ['conflicts' => $conflicts->map(fn($c) => $c->toArray())->all()],
        );

        if (! $force) {
            throw ValidationException::withMessages([
                'conflicts' => $messages->all(),
            ]);
        }
    }

    // ── Read-only feed annotation ──────────────────────────────────────────

    /**
     * Mark every overlapping same-employee pair inside a feed (red badge).
     *
     * @param  Collection<int, CalendarEvent>  $events
     */
    public function annotate(Collection $events): Collection
    {
        $events = $events->values();
        $names  = $this->namesFor($events->flatMap(fn(CalendarEvent $e) => $e->employeeIds)->all());

        $conflictMap = [];

        for ($i = 0; $i < $events->count(); $i++) {
            for ($j = $i + 1; $j < $events->count(); $j++) {
                $a = $events->get($i);
                $b = $events->get($j);

                if (! $a->overlaps($b) || ! $a->sharesEmployeeWith($b)) {
                    continue;
                }

                foreach (array_intersect($a->employeeIds, $b->employeeIds) as $employeeId) {
                    $employeeId = (int) $employeeId;
                    $conflict = $this->makeConflict(
                        $employeeId,
                        $names[$employeeId] ?? "Mitarbeiter #{$employeeId}",
                        $a,
                        $b,
                    );

                    $conflictMap[$a->id][] = $conflict;
                    $conflictMap[$b->id][] = $conflict;
                }
            }
        }

        return $events->map(function (CalendarEvent $event) use ($conflictMap) {
            $conflicts = $conflictMap[$event->id] ?? [];

            return $conflicts === [] ? $event : $event->withConflicts(array_map(
                fn(CalendarConflict $c) => $c->toArray(),
                array_values(array_unique($conflicts, SORT_REGULAR)),
            ));
        });
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function makeConflict(
        int $employeeId,
        string $employeeName,
        CalendarEvent $a,
        CalendarEvent $b
    ): CalendarConflict {
        $overlapStart = $a->startAt->max($b->startAt);
        $overlapEnd   = $a->endAt->min($b->endAt);

        return new CalendarConflict(
            employeeId: $employeeId,
            employeeName: $employeeName,
            eventAId: $a->id,
            eventATitle: $a->title,
            eventAType: $a->type,
            eventBId: $b->id,
            eventBTitle: $b->title,
            eventBType: $b->type,
            overlapStart: $overlapStart,
            overlapEnd: $overlapEnd,
        );
    }

    private function namesFor(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        return User::whereIn('id', $employeeIds)->pluck('name', 'id')->all();
    }
}