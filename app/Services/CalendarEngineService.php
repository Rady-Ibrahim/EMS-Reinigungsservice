<?php

namespace App\Services;

use App\Enums\CalendarEventTypeEnum;
use App\Models\EmployeeShift;
use App\Models\ExtraAuftrag;
use App\Models\FixObjectSchedule;
use App\Models\InternalEvent;
use App\Models\PersonalAppointment;
use App\Models\TeamupSetting;
use App\ValueObjects\CalendarEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * The Unified Calendar engine — aggregates every calendar source into one
 * normalized {@see CalendarEvent} collection for a given date range.
 *
 * Layer keys: fix | extra | shifts | personal | internal | teamup
 *
 * The `personal` and `internal` layers are two views onto the same
 * `personal_events` table (see {@see \App\Models\PersonalEvent}); `personal`
 * holds a user's private entries and can be switched off with the
 * `show_personal` option without affecting company events.
 */
class CalendarEngineService
{
    /** Default layer color per layer key. */
    public const COLORS = [
        'fix'      => '#D4AF37',   // Fixobjekte — the signature EMS gold
        'extra'    => '#3b82f6',
        'shifts'   => '#10b981',
        'personal' => '#8b5cf6',
        'internal' => '#f59e0b',
        'teamup'   => '#64748b',
    ];

    public function __construct(private readonly TeamupClient $teamupClient)
    {
    }

    // ── Layer definitions (UI toggle sheet) ───────────────────────────────

    public function layerDefinitions(): array
    {
        return [
            ['key' => 'fix',      'label' => __('messages.cal.layers.fix'),      'color' => self::COLORS['fix'],      'enabled' => true],
            ['key' => 'extra',    'label' => __('messages.cal.layers.extra'),    'color' => self::COLORS['extra'],    'enabled' => true],
            ['key' => 'shifts',   'label' => __('messages.cal.layers.shifts'),   'color' => self::COLORS['shifts'],   'enabled' => true],
            ['key' => 'personal', 'label' => __('messages.cal.layers.personal'), 'color' => self::COLORS['personal'], 'enabled' => true],
            ['key' => 'internal', 'label' => __('messages.cal.layers.internal'), 'color' => self::COLORS['internal'], 'enabled' => true],
            ['key' => 'teamup',   'label' => __('messages.cal.layers.teamup'),   'color' => self::COLORS['teamup'],   'enabled' => false],
        ];
    }

    // ── Feed queries ───────────────────────────────────────────────────────

    /**
     * All events overlapping [$from, $to].
     *
     * @param  array<string>  $layers  layer keys; empty = all layers
     * @param  int|null  $employeeId  only events booking this employee
     * @param  array{show_personal?: bool, viewer_id?: int|null}  $options
     *        show_personal — include a user's private entries (default true).
     *        viewer_id     — whose private entries may be shown at all;
     *                        null means "no restriction" (admin planning view).
     */
    public function forRange(
        CarbonInterface $from,
        CarbonInterface $to,
        array $layers = [],
        ?int $employeeId = null,
        array $options = []
    ): Collection {
        $layers = empty($layers) ? array_column($this->layerDefinitions(), 'key') : array_values($layers);

        $showPersonal = (bool) ($options['show_personal'] ?? true);
        $viewerId     = array_key_exists('viewer_id', $options) ? $options['viewer_id'] : $employeeId;

        $dateFrom = $from->toDateString();
        $dateTo   = $to->toDateString();
        $tzFrom   = $from->copy()->startOfDay();
        $tzTo     = $to->copy()->endOfDay();

        $events = collect();

        if (in_array('fix', $layers)) {
            $events = $events->merge($this->fixEvents($dateFrom, $dateTo, $employeeId));
        }
        if (in_array('extra', $layers)) {
            $events = $events->merge($this->extraEvents($dateFrom, $dateTo, $employeeId));
        }
        if (in_array('shifts', $layers)) {
            $events = $events->merge($this->shiftEvents($tzFrom, $tzTo, $employeeId));
        }
        if (in_array('personal', $layers) && $showPersonal) {
            $events = $events->merge($this->personalEvents($tzFrom, $tzTo, $employeeId, $viewerId));
        }
        if (in_array('internal', $layers)) {
            $events = $events->merge($this->internalEvents($tzFrom, $tzTo, $employeeId));
        }
        if (in_array('teamup', $layers) && $employeeId === null) {
            $events = $events->merge($this->teamupEvents($from, $to));
        }

        return $events
            ->sortBy(fn(CalendarEvent $e) => $e->startAt->timestamp)
            ->values();
    }

    public function forDay(CarbonInterface $day, array $layers = [], ?int $employeeId = null, array $options = []): Collection
    {
        return $this->forRange($day->copy()->startOfDay(), $day->copy()->endOfDay(), $layers, $employeeId, $options);
    }

    public function forWeek(CarbonInterface $dayInWeek, array $layers = [], ?int $employeeId = null, array $options = []): Collection
    {
        $start = $dayInWeek->copy()->startOfWeek();
        return $this->forRange($start, $start->copy()->endOfWeek(), $layers, $employeeId, $options);
    }

    public function forMonth(CarbonInterface $dayInMonth, array $layers = [], ?int $employeeId = null, array $options = []): Collection
    {
        $start = $dayInMonth->copy()->startOfMonth();
        return $this->forRange($start, $start->copy()->endOfMonth(), $layers, $employeeId, $options);
    }

    // ── Agenda list view ───────────────────────────────────────────────────

    /**
     * Sorted flat agenda (chronological, no grouping needed by client).
     */
    public function agenda(CarbonInterface $from, CarbonInterface $to, array $layers = [], ?int $employeeId = null, array $options = []): Collection
    {
        return $this->forRange($from, $to, $layers, $employeeId, $options)
            ->sortBy(fn(CalendarEvent $e) => [$e->startAt->timestamp, $e->endAt->timestamp])
            ->values();
    }

    // ── Single-source lookup (Teamup push) ────────────────────────────────

    /**
     * Normalize any single source model into one calendar event.
     * Used by the Teamup sync engine and tests.
     */
    public function eventForSource(Model $model): ?CalendarEvent
    {
        return match (true) {
            $model instanceof FixObjectSchedule  => $this->normalizeFix($model->loadMissing(['fixObject.customer:id,name', 'fixObject.location:id,name,city'])),
            $model instanceof ExtraAuftrag       => $this->normalizeExtra($model->loadMissing(['customer:id,name', 'location:id,name,city', 'assignees'])),
            $model instanceof EmployeeShift      => $this->normalizeShift($model->loadMissing(['user.employeeProfile'])),
            $model instanceof PersonalAppointment => $this->normalizePersonal($model),
            $model instanceof InternalEvent      => $this->normalizeInternal($model->loadMissing(['assignees'])),
            default                              => null,
        };
    }

    // ── Layer builders (private) ───────────────────────────────────────────

    private function fixEvents(string $from, string $to, ?int $employeeId): Collection
    {
        $schedules = FixObjectSchedule::forDateRange($from, $to)
            ->with([
                'fixObject.customer:id,name',
                'fixObject.location:id,name,city,street,house_number',
                'fixObject.assignments',
                'scheduleAssignments',
            ])
            ->get();

        $output = collect();

        foreach ($schedules as $schedule) {
            $event = $this->normalizeFix($schedule);
            if (! $event) {
                continue;
            }
            if ($employeeId !== null && ! in_array($employeeId, $event->employeeIds, true)) {
                continue;
            }
            $output->push($event);
        }

        return $output;
    }

    private function extraEvents(string $from, string $to, ?int $employeeId): Collection
    {
        $orders = ExtraAuftrag::whereBetween('scheduled_date', [$from, $to])
            ->with(['customer:id,name', 'location:id,name,city,street,house_number', 'assignees'])
            ->get();

        $output = collect();

        foreach ($orders as $order) {
            $event = $this->normalizeExtra($order);
            if ($employeeId !== null && ! in_array($employeeId, $event->employeeIds, true)) {
                continue;
            }
            $output->push($event);
        }

        return $output;
    }

    private function shiftEvents(CarbonInterface $from, CarbonInterface $to, ?int $employeeId): Collection
    {
        $shifts = EmployeeShift::forDateRange($from, $to)
            ->with(['user.employeeProfile'])
            ->when($employeeId !== null, fn($q) => $q->where('user_id', $employeeId))
            ->get();

        return $shifts->map(fn($shift) => $this->normalizeShift($shift));
    }

    private function personalEvents(CarbonInterface $from, CarbonInterface $to, ?int $employeeId, ?int $viewerId = null): Collection
    {
        $appointments = PersonalAppointment::forDateRange($from, $to)
            ->visibleTo($viewerId)
            ->with(['user:id,name'])
            ->when($employeeId !== null, fn($q) => $q->where('user_id', $employeeId))
            ->get();

        return $appointments->map(fn($appt) => $this->normalizePersonal($appt));
    }

    private function internalEvents(CarbonInterface $from, CarbonInterface $to, ?int $employeeId): Collection
    {
        $events = InternalEvent::forDateRange($from, $to)
            ->with(['user:id,name', 'assignees.user:id,name'])
            ->when($employeeId !== null, fn($q) => $q->forUser($employeeId))
            ->get();

        return $events->map(fn($event) => $this->normalizeInternal($event));
    }

    private function teamupEvents(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $settings = TeamupSetting::current();

        if (! $settings->isConfigured()) {
            return collect();
        }

        try {
            $remoteEvents = $this->teamupClient->fetchEvents($from, $to);
        } catch (\Throwable $e) {
            Log::warning('Teamup-Events konnten nicht geladen werden: '.$e->getMessage());

            return collect();
        }

        $mappedIds = \App\Models\TeamupSyncState::query()
            ->whereNotNull('teamup_event_id')
            ->pluck('teamup_event_id')
            ->map(fn($v) => (string) $v)
            ->all();

        $color = self::COLORS['teamup'];

        return collect($remoteEvents)
            ->filter(fn(array $ev) => ! in_array((string) ($ev['id'] ?? ''), $mappedIds, true))
            ->map(function (array $ev) use ($color) {
                $allDay = (bool) ($ev['all_day'] ?? false);
                $start  = $this->parseTeamupDateTime($ev['start_dt'] ?? null, $allDay, true);
                $end    = $this->parseTeamupDateTime($ev['end_dt'] ?? null, $allDay, false);

                if (! $start || ! $end || $start->gte($end)) {
                    return null;
                }

                return new CalendarEvent(
                    id: 'teamup:'.($ev['id'] ?? 'unknown'),
                    type: CalendarEventTypeEnum::TeamupExternal,
                    layer: 'teamup',
                    title: (string) ($ev['title'] ?? 'Teamup-Termin'),
                    startAt: $start,
                    endAt: $end,
                    allDay: $allDay,
                    color: $ev['color'] ?? $color,
                    location: $ev['location'] ?? null,
                    metadata: ['subcalendar_id' => $ev['subcalendar_id'] ?? null],
                );
            })
            ->filter()
            ->values();
    }

    // ── Normalizers ────────────────────────────────────────────────────────

    private function normalizeFix(FixObjectSchedule $schedule): ?CalendarEvent
    {
        $fixObject = $schedule->fixObject;

        if (! $fixObject) {
            return null;
        }

        [$start, $end, $allDay] = $this->timeWindow(
            $schedule->scheduled_date,
            $schedule->scheduled_start,
            $schedule->scheduled_end
        );

        $location = $fixObject->location?->name;
        if ($fixObject->location?->city) {
            $location = $location ? "{$location}, {$fixObject->location->city}" : $fixObject->location->city;
        }

        return new CalendarEvent(
            id: 'fix_schedule:'.$schedule->id,
            type: CalendarEventTypeEnum::FixSchedule,
            // Must match the `key` published by layerDefinitions(). It used to
            // say 'jobs', which no legend entry and no filter pill uses, so a
            // client could not map the event back to the toggle it came from.
            layer: 'fix',
            title: $fixObject->title,
            startAt: $start,
            endAt: $end,
            allDay: $allDay,
            color: $fixObject->calendar_color ?: self::COLORS['fix'],
            employeeIds: $schedule->effectiveEmployeeIds(),
            status: $schedule->status->value,
            statusLabel: $schedule->status->label(),
            location: $location,
            sourceId: $schedule->id,
            metadata: [
                'customer_id'   => $fixObject->customer_id,
                'customer_name' => $fixObject->customer?->name,
                'fix_object_id' => $fixObject->id,
                'contract_hours'=> $fixObject->contract_hours,
                'detail_url'    => $this->routeTo('admin.fix-objects.show', $fixObject->id),
            ],
        );
    }

    private function normalizeExtra(ExtraAuftrag $order): CalendarEvent
    {
        $date = Carbon::parse($order->scheduled_date);
        $allDay = $order->scheduled_time_start === null;

        if ($allDay) {
            $start = $date->copy()->startOfDay();
            $end   = $date->copy()->endOfDay();
        } else {
            $time  = $order->scheduled_time_start; // 'HH:MM:SS'
            $start = Carbon::parse($date->toDateString().' '.$time);
            $hours = (float) ($order->estimated_hours ?: 1);
            $end   = $start->copy()->addHours(max($hours, 0.25));
        }

        $location = $order->location?->name;
        if ($order->location?->city) {
            $location = $location ? "{$location}, {$order->location->city}" : $order->location->city;
        }

        return new CalendarEvent(
            id: 'extra_auftrag:'.$order->id,
            type: CalendarEventTypeEnum::ExtraAuftrag,
            // Must match the `key` published by layerDefinitions(). See the
            // note in normalizeFix().
            layer: 'extra',
            title: $order->title,
            startAt: $start,
            endAt: $end,
            allDay: $allDay,
            color: self::COLORS['extra'],
            employeeIds: $order->assignees->pluck('user_id')->all(),
            status: $order->status->value,
            statusLabel: $order->status->label(),
            location: $location,
            sourceId: $order->id,
            metadata: [
                'customer_id'       => $order->customer_id,
                'customer_name'     => $order->customer?->name,
                'estimated_hours'   => $order->estimated_hours,
                'detail_url'        => $this->routeTo('admin.extra-auftraege.show', $order->id),
            ],
        );
    }

    private function normalizeShift(EmployeeShift $shift): CalendarEvent
    {
        return new CalendarEvent(
            id: 'employee_shift:'.$shift->id,
            type: CalendarEventTypeEnum::EmployeeShift,
            layer: 'shifts',
            title: $shift->title,
            startAt: $shift->start_at->copy(),
            endAt: $shift->end_at->copy(),
            allDay: $shift->all_day,
            color: $shift->color ?: ($shift->user?->employeeProfile?->calendar_color ?: self::COLORS['shifts']),
            employeeIds: [$shift->user_id],
            ownerId: $shift->user_id,
            status: $shift->status->value,
            statusLabel: $shift->status->label(),
            location: $shift->notes ?: null,
            sourceId: $shift->id,
            metadata: ['employee_name' => $shift->user?->name, 'detail_url' => $this->routeTo('admin.shifts.edit', $shift->id)],
        );
    }

    private function normalizePersonal(PersonalAppointment $appointment): CalendarEvent
    {
        return new CalendarEvent(
            id: 'personal_appointment:'.$appointment->id,
            type: CalendarEventTypeEnum::PersonalAppointment,
            layer: 'personal',
            title: $appointment->title,
            startAt: $appointment->start_at->copy(),
            endAt: $appointment->end_at->copy(),
            allDay: $appointment->all_day,
            color: $appointment->displayColor(),
            employeeIds: [$appointment->user_id],
            ownerId: $appointment->user_id,
            location: $appointment->location,
            sourceId: $appointment->id,
            metadata: [
                'owner_name'   => $appointment->user?->name,
                'is_private'   => (bool) $appointment->is_private,
                'description'  => $appointment->description,
                'detail_url'   => $this->routeTo('admin.appointments.edit', $appointment->id),
            ],
        );
    }

    private function normalizeInternal(InternalEvent $event): CalendarEvent
    {
        return new CalendarEvent(
            id: 'internal_event:'.$event->id,
            type: CalendarEventTypeEnum::InternalEvent,
            layer: 'internal',
            title: $event->title,
            startAt: $event->start_at->copy(),
            endAt: $event->end_at->copy(),
            allDay: $event->all_day,
            color: $event->displayColor(),
            employeeIds: $event->participatingUserIds(),
            ownerId: $event->user_id,
            status: $event->event_type->value,
            statusLabel: $event->event_type->label(),
            location: $event->location,
            sourceId: $event->id,
            metadata: [
                'event_type'   => $event->event_type->value,
                'creator_name' => $event->user?->name,
                'attendees'    => $event->attendeeIds(),
                'description'  => $event->description,
                'detail_url'   => $this->routeTo('admin.calendar.internal-events.index', null),
            ],
        );
    }

    // ── Shared helpers ─────────────────────────────────────────────────────

    /**
     * Build start/end for a date + optional [start,end] times.
     * Missing times ⇒ all-day event (00:00 – 23:59:59 of that date).
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface, 2: bool}
     */
    private function timeWindow(\Illuminate\Support\Carbon|CarbonInterface $date, ?string $startTime, ?string $endTime): array
    {
        $dateStr = Carbon::parse($date)->toDateString();

        if (! $startTime || ! $endTime) {
            $day = Carbon::parse($dateStr);

            return [$day->copy()->startOfDay(), $day->copy()->endOfDay(), true];
        }

        $start = Carbon::parse($dateStr.' '.$startTime);
        $end   = Carbon::parse($dateStr.' '.$endTime);

        // Times crossing midnight (e.g. 22:00 → 02:00)
        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end, false];
    }

    private function routeTo(string $name, ?int $id): ?string
    {
        try {
            return Route::has($name) ? route($name, $id) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parse a Teamup event datetime. Handles naive datetime strings as well
     * as ISO 8601 with offset. For all-day events Teamup uses date-only values
     * with an EXCLUSIVE end date.
     */
    private function parseTeamupDateTime(?string $value, bool $allDay, bool $isStart): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($allDay && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $day = Carbon::parse($value);

            return $isStart ? $day->copy()->startOfDay() : $day->copy()->subDay()->endOfDay();
        }

        return Carbon::parse($value);
    }
}