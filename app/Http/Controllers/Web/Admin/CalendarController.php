<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\CalendarEngineService;
use App\Services\ConflictCheckerService;
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
        ]);

        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to   = Carbon::parse($request->input('to'))->endOfDay();

        $layers = $this->parseLayers($request->input('layers'));
        $employeeId = $request->filled('employee') ? (int) $request->integer('employee') : null;

        $events = $this->conflicts->annotate(
            $this->engine->forRange($from, $to, $layers, $employeeId)
        );

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
            return array_values(array_filter($value, fn($v) => in_array($v, ['fix', 'extra', 'shifts', 'personal', 'internal', 'teamup'], true)));
        }

        return array_values(array_filter(explode(',', (string) $value), fn($v) => in_array($v, ['fix', 'extra', 'shifts', 'personal', 'internal', 'teamup'], true)));
    }
}