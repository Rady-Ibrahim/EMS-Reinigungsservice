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
    ) {}

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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'layers' => ['nullable'],
            'employee' => ['nullable', 'integer'],
            // Deliberately NOT `boolean`: that rule only accepts 1/0/true/false
            // after PHP casts them, and rejects the literal string "true" the
            // mobile client puts in the query string. Accepted values are
            // normalised in showPersonal() below.
            'show_personal' => ['nullable', 'in:1,0,true,false,yes,no,on,off'],
        ]);

        $user = $request->user();

        // The mobile home screen calls this without a range to render "today".
        // Defaulting to a 7-day window keeps the "range required" contract
        // intact for everyone else while letting the app fire a bare call.
        $to = Carbon::parse($request->input('to') ?: now())->endOfDay();
        $from = Carbon::parse($request->input('from') ?: now()->subDays(6))->startOfDay();

        $layers = $this->parseLayers($request->input('layers'));

        // `show_personal=true` is the flag the app sends; it simply forces the
        // personal layer on. The layer is already the default, so this is a
        // no-op unless the app explicitly combined it with `layers=`.
        $showPersonal = $this->showPersonal($request);

        if ($showPersonal && $request->filled('layers') && ! in_array('personal', $layers, true)) {
            $layers[] = 'personal';
        }

        $requestedEmployee = $request->filled('employee') ? (int) $request->integer('employee') : null;

        if ($requestedEmployee !== null && $requestedEmployee !== $user->id && ! $user->tokenCan('reassign:manage')) {
            abort(403, 'Zugriff verweigert.');
        }

        $employeeId = $requestedEmployee ?? $user->id;

        $events = $this->conflicts->annotate(
            $this->engine->forRange($from, $to, $layers, $employeeId)
        );

        return response()->json([
            'data' => $events->map(fn ($e) => $this->mobileSafe($e->toArray()))->values(),
            'layers' => $this->engine->layerDefinitions(),
            'meta' => [
                'conflict_count' => $events->filter(fn ($e) => $e->hasConflicts())->count(),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'personal' => $showPersonal,
            ],
        ]);
    }

    /**
     * Company name and the company payout are internal planning data and must
     * never reach the mobile app (admin-only, see the Companies feature).
     */
    private function mobileSafe(array $event): array
    {
        unset($event['metadata']['company_name'], $event['metadata']['company_payout']);

        return $event;
    }

    /**
     * The query string is text, so `"true"` and `"yes"` must count as true.
     * `$request->boolean()` alone only understands PHP-ish values and would
     * read the mobile app's `?show_personal=true` as false.
     */
    private function showPersonal(Request $request): bool
    {
        $value = $request->query('show_personal');

        if ($value === null || $value === '') {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function parseLayers(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter($value, fn ($v) => in_array($v, ['fix', 'extra', 'shifts', 'personal', 'internal'], true)));
        }

        return array_values(array_filter(explode(',', (string) $value), fn ($v) => in_array($v, ['fix', 'extra', 'shifts', 'personal', 'internal'], true)));
    }
}
