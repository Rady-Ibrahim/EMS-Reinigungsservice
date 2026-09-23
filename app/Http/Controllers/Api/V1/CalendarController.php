<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CalendarEngineService;
use App\Services\ConflictCheckerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(
        private readonly CalendarEngineService $engine,
        private readonly ConflictCheckerService $conflicts
    ) {
    }

    /**
     * GET /api/v1/calendar?from=&to=&layers=
     *
     * Mobile unified calendar for the authenticated employee. Everyone sees
     * their own bookings (their calendar) as annotated events; a Vorarbeiter
     * may additionally request employee=<id> or all employees (omitting
     * employee) to plan.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from'     => ['required', 'date'],
            'to'       => ['required', 'date', 'after_or_equal:from'],
            'layers'   => ['nullable'],
            'employee' => ['nullable', 'integer'],
        ]);

        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to   = Carbon::parse($request->input('to'))->endOfDay();

        $user = $request->user();
        $layers = $this->parseLayers($request->input('layers'));
        $requestedEmployee = $request->filled('employee') ? (int) $request->integer('employee') : null;

        if ($requestedEmployee !== null && $requestedEmployee !== $user->id && ! $user->tokenCan('reassign:manage')) {
            abort(403, 'Zugriff verweigert.');
        }

        $employeeId = $requestedEmployee ?? $user->id;

        $events = $this->conflicts->annotate(
            $this->engine->forRange($from, $to, $layers, $employeeId)
        );

        return response()->json([
            'data'    => $events->map(fn($e) => $e->toArray())->values(),
            'layers'  => $this->engine->layerDefinitions(),
            'meta'    => ['conflict_count' => $events->filter(fn($e) => $e->hasConflicts())->count()],
        ]);
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