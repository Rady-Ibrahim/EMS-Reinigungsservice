<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use App\Services\MonthlyReportEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MonthlyReportController extends Controller
{
    public function __construct(
        private readonly MonthlyReportEngineService $engine
    ) {
    }

    /**
     * GET /api/v1/monthly-reports?year=2026&month=10
     * The authenticated employee's monthly report (with line items),
     * including its approval/freeze state.
     */
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('reports:view'), Response::HTTP_FORBIDDEN);

        $request->validate([
            'year'  => ['nullable', 'integer', 'min:2020', 'max:2099'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year  = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);
        $user  = $request->user();

        $report = $this->engine->buildReport($user, $year, $month);

        return response()->json([
            'data' => [
                'user_id'              => $user->id,
                'period'               => sprintf('%s-%02d', $year, $month),
                'period_label'         => $report->periodLabel(),
                'fix_paid_hours'       => (float) $report->fix_paid_hours,
                'fix_actual_hours'     => (float) $report->fix_actual_hours,
                'extra_work_hours'     => (float) $report->extra_work_hours,
                'extra_travel_hours'   => (float) $report->extra_travel_hours,
                'extra_paid_hours'     => (float) $report->extra_paid_hours,
                'extra_actual_hours'   => (float) $report->extra_actual_hours,
                'total_paid_hours'     => (float) $report->total_paid_hours,
                'is_approved'          => $report->isApproved(),
                'approved_by'          => $report->approver?->name,
                'approved_at'          => $report->approved_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v1/monthly-reports/line-items?year=2026&month=10
     * Per-execution breakdown for the authenticated employee's month.
     */
    public function lineItems(Request $request): JsonResponse
    {
        abort_unless($request->user()->tokenCan('reports:view'), Response::HTTP_FORBIDDEN);

        $request->validate([
            'year'  => ['nullable', 'integer', 'min:2020', 'max:2099'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year  = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);
        $user  = $request->user();

        $items = $this->engine->lineItemsFor($user, $year, $month);

        return response()->json([
            'data' => $items->map(fn(array $item) => [
                'job_type'      => $item['job_type'],
                'job_id'        => $item['job_id'],
                'date'          => $item['date'],
                'label'         => $item['label'],
                'customer'      => $item['customer'],
                'actual_hours'  => $item['actual_hours'],
                'paid_hours'    => $item['paid_hours'],
                'travel_hours'  => $item['travel_hours'],
                'contract_hours'=> $item['contract_hours'],
            ]),
        ]);
    }
}