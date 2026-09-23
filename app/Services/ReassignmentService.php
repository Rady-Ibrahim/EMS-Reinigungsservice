<?php

namespace App\Services;

use App\Models\ExtraAuftragAssignee;
use App\Models\FixObject;
use App\Models\FixObjectAssignment;
use App\Models\FixObjectSchedule;
use App\Models\FixObjectScheduleAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dynamic Assignment — re-assign a task to another employee at ANY time
 * without recreating the order (Phase 6).
 *
 *  - reassignContract:       whole Fixobjekt contract-level switch
 *  - reassignSchedule:       single schedule day override (per-day)
 *  - reassignExtraAssignee:  swap one employee inside an Extra-Auftrag
 */
class ReassignmentService
{
    public function __construct(
        private readonly ConflictCheckerService $conflicts,
        private readonly TeamupSyncService $teamup
    ) {
    }

    public function reassignContract(
        FixObject $fixObject,
        int $newUserId,
        string $from,
        ?string $until = null,
        ?string $reason = null,
        bool $force = false
    ): FixObjectAssignment {
        return DB::transaction(function () use ($fixObject, $newUserId, $from, $until, $reason, $force) {
            $candidates = $this->conflicts->fixCandidates($fixObject, $from, $until, [$newUserId]);

            if ($candidates->isNotEmpty()) {
                $ignore = $fixObject->schedules()
                    ->pluck('id')
                    ->map(fn($id) => 'fix_schedule:'.$id)
                    ->all();

                $this->conflicts->assertClean(
                    $this->conflicts->findConflicts($candidates, $ignore),
                    $force
                );
            }

            // Relieve the previous employee(s) from this contract
            $closeFrom = Carbon::parse($from)->subDay()->toDateString();

            FixObjectAssignment::where('fix_object_id', $fixObject->id)
                ->where('assigned_from', '<=', $closeFrom)
                ->where(function ($q) use ($closeFrom) {
                    $q->whereNull('assigned_until')
                      ->orWhere('assigned_until', '>', $closeFrom);
                })
                ->update(['assigned_until' => $closeFrom]);

            $assignment = FixObjectAssignment::create([
                'fix_object_id' => $fixObject->id,
                'user_id'       => $newUserId,
                'assigned_from' => $from,
                'assigned_until'=> $until,
                'notes'         => $reason,
            ]);

            return $assignment->load(['fixObject:id,title', 'user:id,name']);
        });
    }

    public function reassignSchedule(
        FixObjectSchedule $schedule,
        int $newUserId,
        ?string $reason = null,
        bool $force = false
    ): FixObjectScheduleAssignment {
        $fixObject = $schedule->fixObject;

        // Redundant re-assignment to the same effective employee
        if (in_array($newUserId, $schedule->effectiveEmployeeIds(), true)) {
            throw ValidationException::withMessages([
                'user_id' => 'Der Mitarbeiter ist diesem Termin bereits zugeordnet.',
            ]);
        }

        $dateStr = $schedule->scheduled_date->toDateString();

        if ($schedule->scheduled_start && $schedule->scheduled_end) {
            $candidate = new \App\ValueObjects\CalendarEvent(
                id: 'fix_schedule:'.$schedule->id,
                type: \App\Enums\CalendarEventTypeEnum::FixSchedule,
                layer: 'jobs',
                title: $fixObject->title,
                startAt: Carbon::parse($dateStr.' '.$schedule->scheduled_start),
                endAt: Carbon::parse($dateStr.' '.$schedule->scheduled_end),
                allDay: false,
                color: $fixObject->calendar_color ?: '#FFD700',
                employeeIds: [$newUserId],
            );
        } else {
            $start = Carbon::parse($dateStr)->startOfDay();
            $candidate = new \App\ValueObjects\CalendarEvent(
                id: 'fix_schedule:'.$schedule->id,
                type: \App\Enums\CalendarEventTypeEnum::FixSchedule,
                layer: 'jobs',
                title: $fixObject->title,
                startAt: $start,
                endAt: $start->copy()->endOfDay(),
                allDay: true,
                color: $fixObject->calendar_color ?: '#FFD700',
                employeeIds: [$newUserId],
            );
        }

        $this->conflicts->assertClean(
            $this->conflicts->findConflicts(collect([$candidate]), ['fix_schedule:'.$schedule->id]),
            $force
        );

        $assignment = FixObjectScheduleAssignment::create([
            'schedule_id' => $schedule->id,
            'user_id'     => $newUserId,
            'created_by'  => auth()->id(),
            'reason'      => $reason,
        ]);

        // Overrides change the booked employee for that day → queue Teamup push
        $this->teamup->markPending($schedule);

        return $assignment->load(['user:id,name']);
    }

    public function reassignExtraAssignee(
        ExtraAuftragAssignee $assignee,
        int $newUserId,
        bool $force = false
    ): ExtraAuftragAssignee {
        $order = $assignee->extraAuftrag;

        if (! $order) {
            throw new \RuntimeException('Auftrag nicht gefunden.');
        }

        if ($order->assignees()->where('user_id', $newUserId)->where('id', '<>', $assignee->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'Der Mitarbeiter ist diesem Auftrag bereits zugeordnet.',
            ]);
        }

        $candidate = $this->extraCandidate($order, $newUserId);

        $this->conflicts->assertClean(
            $this->conflicts->findConflicts(collect([$candidate]), ['extra_auftrag:'.$order->id]),
            $force
        );

        $assignee->update(['user_id' => $newUserId]);
        $this->teamup->markPending($order);

        return $assignee->fresh(['user:id,name', 'extraAuftrag:id,title']);
    }

    private function extraCandidate(\App\Models\ExtraAuftrag $order, int $newUserId): \App\ValueObjects\CalendarEvent
    {
        $date = Carbon::parse($order->scheduled_date);

        if ($order->scheduled_time_start) {
            $start = Carbon::parse($date->toDateString().' '.$order->scheduled_time_start);
            $hours = (float) ($order->estimated_hours ?: 1);
            $end   = $start->copy()->addHours(max($hours, 0.25));
            $allDay = false;
        } else {
            $start = $date->copy()->startOfDay();
            $end   = $date->copy()->endOfDay();
            $allDay = true;
        }

        return new \App\ValueObjects\CalendarEvent(
            id: 'extra_auftrag:'.$order->id,
            type: \App\Enums\CalendarEventTypeEnum::ExtraAuftrag,
            layer: 'jobs',
            title: $order->title,
            startAt: $start,
            endAt: $end,
            allDay: $allDay,
            color: '#3b82f6',
            employeeIds: [$newUserId],
        );
    }
}