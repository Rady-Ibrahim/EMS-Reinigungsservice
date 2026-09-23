<?php

namespace App\ValueObjects;

/**
 * Immutable value object representing the time summary for one job execution.
 * Produced by TimeTrackingEngine — never persisted directly.
 */
final readonly class TimeTrackingSummary
{
    public function __construct(
        public string $jobType,          // 'fix_object' | 'extra_auftrag'
        public int    $jobId,
        public int    $userId,
        public string $date,             // Y-m-d
        public float  $actualHours,      // what the employee actually worked
        public float  $paidHours,        // what counts toward reports & payroll
        public float  $travelHours,      // paid travel time (0 if unpaid)
        public float  $contractHours,    // Fix: from contract | Extra: 0
        public bool   $hasTravelTime,
        public bool   $travelIsPaid,
    ) {
    }

    /**
     * Deviation between actual and paid hours.
     * Positive = worked more than paid, Negative = worked less.
     * Only meaningful for Fix objects (contract hours anchor).
     */
    public function deviationHours(): float
    {
        return round($this->actualHours - $this->paidHours, 2);
    }

    /**
     * Total paid minutes (for DB storage / aggregation).
     */
    public function paidMinutes(): int
    {
        return (int) round($this->paidHours * 60);
    }

    public function toArray(): array
    {
        return [
            'job_type'        => $this->jobType,
            'job_id'          => $this->jobId,
            'user_id'         => $this->userId,
            'date'            => $this->date,
            'actual_hours'    => $this->actualHours,
            'paid_hours'      => $this->paidHours,
            'travel_hours'    => $this->travelHours,
            'contract_hours'  => $this->contractHours,
            'deviation_hours' => $this->deviationHours(),
            'travel_is_paid'  => $this->travelIsPaid,
        ];
    }
}
