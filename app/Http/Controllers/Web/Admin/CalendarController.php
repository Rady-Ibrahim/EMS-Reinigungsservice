<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\CalendarEventTypeEnum;
use App\Enums\ScheduleStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CalendarEventMark;
use App\Models\FixObjectSchedule;
use App\Models\User;
use App\Services\CalendarEngineService;
use App\Services\ConflictCheckerService;
use App\Services\ScheduleGeneratorService;
use App\ValueObjects\CalendarEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __construct(
        private readonly CalendarEngineService $engine,
        private readonly ConflictCheckerService $conflicts
    ) {
    }

    public function index(): View
    {
        return view('admin.calendar.index', [
            'layers' => $this->engine->layerDefinitions(),
            'employees' => User::query()
                ->whereIn('role', ['vorarbeiter', 'mitarbeiter'])
                ->where('is_active', true)
                ->with('employeeProfile:id,user_id,calendar_color')
                ->orderBy('name')
                ->get(['id', 'name', 'role']),
        ]);
    }

    /**
     * JSON feed for FullCalendar: unified events, annotated with conflicts.
     */
    public function events(Request $request): JsonResponse
    {
        $request->validate([
            'from'     => ['required', 'date'],
            'to'       => ['required', 'date', 'after_or_equal:from'],
            'layers'   => ['nullable'],
            'employee' => ['nullable', 'exists:users,id'],
            'employee_filter' => ['nullable', 'boolean'],
            'employees' => ['nullable', 'array'],
            'employees.*' => ['integer', 'exists:users,id'],
        ]);

        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to   = Carbon::parse($request->input('to'))->endOfDay();

        $layers = $this->parseLayers($request->input('layers'));
        $employeeId = $request->filled('employee') ? (int) $request->integer('employee') : null;

        if ($layers === [] || in_array('fix', $layers, true)) {
            app(ScheduleGeneratorService::class)->generateForRange($from, $to);
        }

        $events = $this->engine->forRange($from, $to, $layers, $employeeId);
        if ($request->boolean('employee_filter')) {
            $selectedEmployees = array_map('intval', $request->input('employees', []));
            $events = $events->filter(function ($event) use ($selectedEmployees) {
                return $event->employeeIds === []
                    || array_intersect($event->employeeIds, $selectedEmployees) !== [];
            })->values();
        }
        $events = $this->conflicts->annotate($events);
        $eventIds = $events->pluck('sourceId')->filter()->unique()->values();
        $markedEvents = CalendarEventMark::query()
            ->whereIn('event_type', $events->map(fn ($event) => $event->type->value)->unique())
            ->whereIn('event_id', $eventIds)
            ->get()
            ->keyBy(fn (CalendarEventMark $mark) => $mark->event_type.':'.$mark->event_id);
        $eventData = $events->map(function (CalendarEvent $event) use ($markedEvents) {
            $data = $event->toArray();
            $isMarked = $markedEvents->has($event->type->value.':'.$event->sourceId);
            $data['metadata']['is_marked'] = $isMarked;
            $data['metadata']['mark_url'] = route('admin.calendar.marks.store', [
                'eventType' => $event->type->value,
                'eventId' => $event->sourceId,
            ]);
            $data['metadata']['unmark_url'] = route('admin.calendar.marks.destroy', [
                'eventType' => $event->type->value,
                'eventId' => $event->sourceId,
            ]);

            if ($isMarked) {
                $data['title'] = '★ '.$data['title'];
            }

            return $data;
        });

        return response()->json([
            'events'      => $eventData->all(),
            'annotated'   => $events->filter(fn($e) => $e->hasConflicts())->count(),
        ]);
    }

    public function editSchedule(FixObjectSchedule $schedule): View
    {
        abort_unless($schedule->status === ScheduleStatusEnum::Pending, 404);
        $schedule->load('fixObject:id,title');

        return view('admin.calendar.schedule_form', compact('schedule'));
    }

    public function updateSchedule(Request $request, FixObjectSchedule $schedule): \Illuminate\Http\RedirectResponse
    {
        abort_unless($schedule->status === ScheduleStatusEnum::Pending, 404);

        $validated = $request->validate([
            'scheduled_date' => [
                'required',
                'date',
                Rule::unique('fix_object_schedules', 'scheduled_date')
                    ->where('fix_object_id', $schedule->fix_object_id)
                    ->ignore($schedule->id),
            ],
            'scheduled_start' => ['nullable', 'date_format:H:i', 'required_with:scheduled_end'],
            'scheduled_end' => ['nullable', 'date_format:H:i', 'required_with:scheduled_start'],
        ]);

        $event = $this->engine->eventForSource($schedule);
        abort_if($event === null, 404);

        $allDay = empty($validated['scheduled_start']) || empty($validated['scheduled_end']);
        $start = $allDay
            ? Carbon::parse($validated['scheduled_date'])->startOfDay()
            : Carbon::parse($validated['scheduled_date'].' '.$validated['scheduled_start']);
        $end = $allDay
            ? Carbon::parse($validated['scheduled_date'])->endOfDay()
            : Carbon::parse($validated['scheduled_date'].' '.$validated['scheduled_end']);
        if (! $allDay && $end->lte($start)) {
            $end->addDay();
        }

        $candidate = new CalendarEvent(
            id: 'fix_schedule:'.$schedule->id,
            type: CalendarEventTypeEnum::FixSchedule,
            layer: $event->layer,
            title: $event->title,
            startAt: $start,
            endAt: $end,
            allDay: $allDay,
            color: $event->color,
            employeeIds: $event->employeeIds,
            ownerId: $event->ownerId,
            status: $event->status,
            statusLabel: $event->statusLabel,
            location: $event->location,
            sourceId: $schedule->id,
            metadata: $event->metadata,
        );
        $conflicts = $this->conflicts->findConflicts(
            collect([$candidate]),
            ['fix_schedule:'.$schedule->id]
        );
        $this->conflicts->assertClean($conflicts, $request->boolean('force'));

        $schedule->update([
            'scheduled_date' => $validated['scheduled_date'],
            'source_date' => $schedule->source_date ?? $schedule->scheduled_date,
            'scheduled_start' => $validated['scheduled_start'] ?? null,
            'scheduled_end' => $validated['scheduled_end'] ?? null,
            'is_manually_adjusted' => true,
        ]);

        return redirect()->route('admin.calendar.index')->with('success', __('messages.success'));
    }

    public function layers(): JsonResponse
    {
        return response()->json(['layers' => $this->engine->layerDefinitions()]);
    }

    private function parseLayers(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter($value, fn($v) => in_array($v, ['fix', 'extra', 'shifts', 'personal', 'internal'], true)));
        }

        return array_values(array_filter(explode(',', (string) $value), fn($v) => in_array($v, ['fix', 'extra', 'shifts', 'personal', 'internal'], true)));
    }
}