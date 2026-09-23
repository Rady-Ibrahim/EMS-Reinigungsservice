<?php

namespace App\Services;

use App\Models\ExtraAuftragExecution;
use App\Models\FixObjectExecution;
use App\Models\TravelTrack;
use App\Models\User;
use App\ValueObjects\TimeTrackingSummary;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Central time tracking engine.
 *
 * Unifies time calculation across both job types:
 *  - Fixobjekte   → paid hours = contract_hours_applied (frozen)
 *  - Extra-Aufträge → paid hours = work + (travel if is_paid)
 *
 * This engine is read-only — it computes and returns summaries.
 * Write operations are handled by FixObjectService / ExtraAuftragService.
 */
class TimeTrackingEngine
{
    public function __construct(
        private readonly TravelTimeCalculatorService $travelCalculator
    ) {
    }

    // ── Per-execution summaries ────────────────────────────────────────────

    /**
     * Build a TimeTrackingSummary for a Fixobjekt execution.
     *
     * Rule: paid_hours = contract_hours_applied (frozen at start, never changes).
     * actual_hours is recorded for internal audit only.
     */
    public function summarizeFixExecution(FixObjectExecution $execution): TimeTrackingSummary
    {
        $actualMinutes   = $execution->actualDurationMinutes() ?? 0;
        $contractHours   = (float) $execution->contract_hours_applied;

        return new TimeTrackingSummary(
            jobType:       'fix_object',
            jobId:         $execution->schedule_id,
            userId:        $execution->user_id,
            date:          $execution->actual_start->toDateString(),
            actualHours:   round($actualMinutes / 60, 2),
            paidHours:     $contractHours,               // always contract hours
            travelHours:   0.0,                          // no travel for Fixobjekte
            contractHours: $contractHours,
            hasTravelTime: false,
            travelIsPaid:  false,
        );
    }

    /**
     * Build a TimeTrackingSummary for an Extra-Auftrag execution.
     *
     * Rule: paid_hours = work_minutes + (travel_minutes if is_paid).
     */
    public function summarizeExtraExecution(ExtraAuftragExecution $execution): TimeTrackingSummary
    {
        $workMinutes   = $execution->work_minutes ?? 0;
        $travelTrack   = TravelTrack::where('extra_auftrag_id', $execution->extra_auftrag_id)
                                    ->where('user_id', $execution->user_id)
                                    ->first();

        $travelMinutes = $travelTrack?->travel_minutes ?? 0;
        $travelIsPaid  = (bool) ($travelTrack?->is_paid ?? false);
        $travelPaid    = $travelIsPaid ? $travelMinutes : 0;
        $paidMinutes   = $workMinutes + $travelPaid;

        return new TimeTrackingSummary(
            jobType:       'extra_auftrag',
            jobId:         $execution->extra_auftrag_id,
            userId:        $execution->user_id,
            date:          $execution->work_start?->toDateString() ?? now()->toDateString(),
            actualHours:   round($workMinutes / 60, 2),
            paidHours:     round($paidMinutes / 60, 2),
            travelHours:   round(($travelIsPaid ? $travelMinutes : 0) / 60, 2),
            contractHours: 0.0,
            hasTravelTime: $travelMinutes > 0,
            travelIsPaid:  $travelIsPaid,
        );
    }

    // ── Monthly aggregations ───────────────────────────────────────────────

    /**
     * On-demand monthly summary for a single employee.
     * Returns aggregate paid hours broken down by job type.
     *
     * @return array{
     *     fix_paid_hours: float,
     *     extra_paid_hours: float,
     *     total_paid_hours: float,
     *     fix_actual_hours: float,
     *     extra_actual_hours: float,
     *     fix_count: int,
     *     extra_count: int,
     * }
     */
    public function monthlyTotalsForEmployee(User $user, int $year, int $month): array
    {
        // ── Fix executions ─────────────────────────────────────────────────
        $fixExecutions = FixObjectExecution::where('user_id', $user->id)
            ->whereNotNull('actual_end')
            ->whereYear('actual_start', $year)
            ->whereMonth('actual_start', $month)
            ->get();

        $fixPaidHours   = 0.0;
        $fixActualHours = 0.0;

        foreach ($fixExecutions as $exec) {
            $summary         = $this->summarizeFixExecution($exec);
            $fixPaidHours   += $summary->paidHours;
            $fixActualHours += $summary->actualHours;
        }

        // ── Extra executions ───────────────────────────────────────────────
        $extraExecutions = ExtraAuftragExecution::where('user_id', $user->id)
            ->whereNotNull('work_end')
            ->whereYear('work_start', $year)
            ->whereMonth('work_start', $month)
            ->get();

        $extraPaidHours   = 0.0;
        $extraActualHours = 0.0;

        foreach ($extraExecutions as $exec) {
            $summary            = $this->summarizeExtraExecution($exec);
            $extraPaidHours    += $summary->paidHours;
            $extraActualHours  += $summary->actualHours;
        }

        return [
            'fix_paid_hours'    => round($fixPaidHours, 2),
            'fix_actual_hours'  => round($fixActualHours, 2),
            'extra_paid_hours'  => round($extraPaidHours, 2),
            'extra_actual_hours'=> round($extraActualHours, 2),
            'total_paid_hours'  => round($fixPaidHours + $extraPaidHours, 2),
            'fix_count'         => $fixExecutions->count(),
            'extra_count'       => $extraExecutions->count(),
        ];
    }

    /**
     * All summaries for a date range — used for Admin time tracking review.
     * Returns a flat Collection of TimeTrackingSummary objects.
     *
     * @return Collection<TimeTrackingSummary>
     */
    public function summariesForDateRange(User $user, Carbon $from, Carbon $to): Collection
    {
        $summaries = collect();

        // Fix executions
        FixObjectExecution::where('user_id', $user->id)
            ->whereNotNull('actual_end')
            ->whereBetween('actual_start', [$from, $to])
            ->get()
            ->each(fn($exec) => $summaries->push($this->summarizeFixExecution($exec)));

        // Extra executions
        ExtraAuftragExecution::where('user_id', $user->id)
            ->whereNotNull('work_end')
            ->whereBetween('work_start', [$from, $to])
            ->get()
            ->each(fn($exec) => $summaries->push($this->summarizeExtraExecution($exec)));

        return $summaries->sortBy('date')->values();
    }
}
