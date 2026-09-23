<?php

namespace App\Services;

use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Models\TravelTrack;

/**
 * Pure stateless calculator for travel and paid time.
 * No side effects — takes model instances and returns computed values.
 *
 * Rules:
 *  - Only INBOUND travel (Anfahrt) counts — return trip is NEVER paid.
 *  - Travel is paid only if extra_auftraege.is_travel_time_paid = true.
 *  - travel_minutes is frozen at arrival — changes to order settings don't affect it.
 *  - paid_minutes = work_minutes + paid_travel_minutes
 */
class TravelTimeCalculatorService
{
    /**
     * Calculate travel minutes from departure to arrival.
     * Returns null if travel is not yet completed (no arrival).
     */
    public function calculateTravelMinutes(TravelTrack $track): ?int
    {
        if (! $track->arrival_at) {
            return null;
        }

        return max(0, (int) $track->departure_at->diffInMinutes($track->arrival_at));
    }

    /**
     * Calculate paid travel minutes for an employee on a specific order.
     * Returns 0 if travel is not paid or not yet completed.
     */
    public function paidTravelMinutes(ExtraAuftrag $order, TravelTrack $track): int
    {
        if (! $order->is_travel_time_paid) {
            return 0;
        }

        return $track->paidMinutes();
    }

    /**
     * Calculate total paid minutes for an execution.
     *
     * paid_minutes = work_minutes + paid_travel_minutes
     *
     * @param  ExtraAuftragExecution  $execution
     * @param  TravelTrack|null       $travelTrack  — null if employee had no travel
     * @return int
     */
    public function calculatePaidMinutes(ExtraAuftragExecution $execution, ?TravelTrack $travelTrack): int
    {
        $workMinutes = $execution->work_minutes ?? 0;

        $travelPaid = 0;
        if ($travelTrack && $travelTrack->is_paid) {
            $travelPaid = $travelTrack->travel_minutes ?? 0;
        }

        return $workMinutes + $travelPaid;
    }

    /**
     * Convert minutes to decimal hours (e.g. 90 → 1.5).
     */
    public function minutesToHours(int $minutes): float
    {
        return round($minutes / 60, 2);
    }

    /**
     * Build a human-readable summary string for reporting.
     */
    public function formatSummary(int $workMinutes, int $travelMinutes, bool $travelPaid): string
    {
        $workH    = $this->minutesToHours($workMinutes);
        $travelH  = $this->minutesToHours($travelMinutes);
        $paidH    = $travelPaid
            ? $this->minutesToHours($workMinutes + $travelMinutes)
            : $workH;

        return sprintf(
            'Arbeit: %.2fh | Anfahrt: %.2fh (%s) | Bezahlt: %.2fh',
            $workH,
            $travelH,
            $travelPaid ? 'bezahlt' : 'unbezahlt',
            $paidH
        );
    }
}
