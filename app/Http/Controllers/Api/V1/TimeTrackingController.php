<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitTimeAdjustmentRequest;
use App\Models\TimeAdjustmentRequest;
use App\Services\TimeAdjustmentService;
use App\Services\TimeTrackingEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TimeTrackingController extends Controller
{
    public function __construct(
        private readonly TimeTrackingEngine     $engine,
        private readonly TimeAdjustmentService  $adjustmentService
    ) {
    }

    /**
     * GET /api/v1/time-summary?year=2026&month=10
     * Monthly time summary for the authenticated employee.
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $request->validate([
            'year'  => ['nullable', 'integer', 'min:2020', 'max:2099'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year  = $request->integer('year',  now()->year);
        $month = $request->integer('month', now()->month);
        $user  = $request->user();

        $totals = $this->engine->monthlyTotalsForEmployee($user, $year, $month);

        return response()->json([
            'data' => array_merge($totals, [
                'year'  => $year,
                'month' => $month,
            ]),
        ]);
    }

    // ── Time adjustment requests ───────────────────────────────────────────

    /**
     * GET /api/v1/time-adjustments
     * Employee's own adjustment requests.
     */
    public function indexAdjustments(Request $request): JsonResponse
    {
        $requests = $this->adjustmentService->employeeRequests($request->user()->id);

        return response()->json([
            'data' => $requests->getCollection()->map(fn($r) => $this->formatRequest($r)),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page'    => $requests->lastPage(),
                'total'        => $requests->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/time-adjustments
     * Employee submits a new time adjustment request.
     */
    public function submitAdjustment(SubmitTimeAdjustmentRequest $request): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'employee_id' => $request->user()->id,
        ]);

        $adjustment = $this->adjustmentService->submit($data);

        return response()->json([
            'message' => 'Antrag erfolgreich eingereicht.',
            'data'    => $this->formatRequest($adjustment),
        ], Response::HTTP_CREATED);
    }

    // ── Private formatters ─────────────────────────────────────────────────

    private function formatRequest(TimeAdjustmentRequest $req): array
    {
        return [
            'id'               => $req->id,
            'job_type'         => $req->job_type,
            'job_id'           => $req->job_id,
            'requested_start'  => $req->requested_start->toIso8601String(),
            'requested_end'    => $req->requested_end->toIso8601String(),
            'original_start'   => $req->original_start?->toIso8601String(),
            'original_end'     => $req->original_end?->toIso8601String(),
            'reason'           => $req->reason,
            'status'           => $req->status->value,
            'status_label'     => $req->status->label(),
            'admin_note'       => $req->admin_note,
            'reviewed_at'      => $req->reviewed_at?->toIso8601String(),
            'reviewer'         => $req->reviewer?->name,
            'submitted_at'     => $req->client_submitted_at?->toIso8601String() ?? $req->created_at->toIso8601String(),
        ];
    }
}
