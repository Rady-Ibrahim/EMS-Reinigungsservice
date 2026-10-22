<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CalendarEngineService;
use App\Services\ConflictCheckerService;
use App\Services\ScheduleGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json([
            'events'      => $events->map(fn($e) => $e->toArray())->all(),
            'annotated'   => $events->filter(fn($e) => $e->hasConflicts())->count(),
        ]);
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