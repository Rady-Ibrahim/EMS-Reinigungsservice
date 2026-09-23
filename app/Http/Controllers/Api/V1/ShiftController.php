<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreShiftRequest;
use App\Models\EmployeeShift;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $service)
    {
    }

    /**
     * GET /api/v1/shifts — own shifts for a date range.
     */
    public function index(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->addMonths(2)->endOfMonth()->toDateString());

        $shifts = EmployeeShift::forEmployee($request->user()->id)
            ->forDateRange($from, $to)
            ->orderBy('start_at')
            ->get()
            ->map(fn($s) => $this->format($s));

        return response()->json(['data' => $shifts]);
    }

    /**
     * POST /api/v1/shifts — only Vorarbeiter may schedule shifts.
     */
    public function store(StoreShiftRequest $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('reassign:manage'), Response::HTTP_FORBIDDEN);

        try {
            $shift = $this->service->create(
                $request->validated() + ['created_by' => $request->user()->id, 'status' => \App\Enums\ShiftStatusEnum::Planned],
                false,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Schicht überschneidet sich mit einer bestehenden Buchung.', 'errors' => $e->errors()], Response::HTTP_CONFLICT);
        }

        return response()->json(['data' => $this->format($shift)], Response::HTTP_CREATED);
    }

    /**
     * DELETE /api/v1/shifts/{shift} — only Vorarbeiter may delete.
     */
    public function destroy(Request $request, EmployeeShift $shift): JsonResponse
    {
        abort_unless($request->user()->tokenCan('reassign:manage'), Response::HTTP_FORBIDDEN);

        $this->service->delete($shift);

        return response()->json(['message' => 'Schicht gelöscht.']);
    }

    private function format(EmployeeShift $shift): array
    {
        return [
            'id'       => $shift->id,
            'title'    => $shift->title,
            'start_at' => $shift->start_at->toIso8601String(),
            'end_at'   => $shift->end_at->toIso8601String(),
            'all_day'  => $shift->all_day,
            'color'    => $shift->color,
            'status'   => $shift->status->value,
            'notes'    => $shift->notes,
        ];
    }
}