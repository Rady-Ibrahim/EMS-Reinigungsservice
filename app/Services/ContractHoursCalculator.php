<?php

namespace App\Services;

use App\Enums\FixFrequencyEnum;
use App\Models\FixObject;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Pure, stateless calculator for contract hours.
 * No DB calls — operates only on FixObject data and Carbon dates.
 * Fully unit-testable without database.
 */
class ContractHoursCalculator
{
    /**
     * Calculate the total contracted hours for a specific month.
     *
     * Rule: occurrences_in_month × fix_object.contract_hours
     * This value is the financial/reporting anchor — actual work time is irrelevant.
     *
     * @param  FixObject  $fixObject
     * @param  int        $year
     * @param  int        $month   1–12
     * @return float
     */
    public function monthlyHours(FixObject $fixObject, int $year, int $month): float
    {
        $occurrences = $this->countOccurrences($fixObject, $year, $month);
        return round($occurrences * (float) $fixObject->contract_hours, 2);
    }

    /**
     * Calculate total contracted hours for a full year.
     */
    public function annualHours(FixObject $fixObject, int $year): float
    {
        $total = 0.0;
        for ($month = 1; $month <= 12; $month++) {
            $total += $this->monthlyHours($fixObject, $year, $month);
        }
        return round($total, 2);
    }

    /**
     * Count how many times this FixObject runs within a given month,
     * respecting contract validity dates and frequency rules.
     */
    public function countOccurrences(FixObject $fixObject, int $year, int $month): int
    {
        $dates = $this->getOccurrenceDates($fixObject, $year, $month);
        return count($dates);
    }

    /**
     * Get all concrete Carbon dates this FixObject should run in the given month.
     *
     * @return Carbon[]
     */
    public function getOccurrenceDates(FixObject $fixObject, int $year, int $month): array
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd   = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        // Clamp to contract validity window
        $from = $periodStart->max($fixObject->valid_from->startOfDay());
        $to   = $periodEnd->min(
            $fixObject->valid_until
                ? $fixObject->valid_until->endOfDay()
                : $periodEnd
        );

        if ($from->gt($to)) {
            return [];
        }

        return match($fixObject->frequency) {
            FixFrequencyEnum::Daily     => $this->dailyDates($fixObject, $from, $to),
            FixFrequencyEnum::Weekly,
            FixFrequencyEnum::Biweekly,
            FixFrequencyEnum::Triweekly => $this->weeklyDates($fixObject, $from, $to),
            FixFrequencyEnum::Monthly   => $this->monthlyDates($fixObject, $from, $to),
        };
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Daily: every day (Mon–Sun) or every configured weekday.
     * If frequency_days is empty → every calendar day.
     */
    private function dailyDates(FixObject $fix, Carbon $from, Carbon $to): array
    {
        $configuredDays = $fix->frequency_days ?? [];
        $dates = [];

        foreach (CarbonPeriod::create($from, '1 day', $to) as $date) {
            if (empty($configuredDays) || in_array($date->shortEnglishDayOfWeek, $configuredDays)) {
                $dates[] = $date->copy();
            }
        }

        return $dates;
    }

    /**
     * Weekly-based: 1x / 2x / 3x per week — driven by frequency_days list.
     * frequency_days determines WHICH days of the week to run on.
     */
    private function weeklyDates(FixObject $fix, Carbon $from, Carbon $to): array
    {
        $configuredDays = $fix->frequency_days ?? [];

        if (empty($configuredDays)) {
            return [];
        }

        $dates = [];

        foreach (CarbonPeriod::create($from, '1 day', $to) as $date) {
            if (in_array($date->shortEnglishDayOfWeek, $configuredDays)) {
                $dates[] = $date->copy();
            }
        }

        return $dates;
    }

    /**
     * Monthly: occurs once per month — on valid_from day-of-month if within range,
     * otherwise on the 1st of the month.
     */
    private function monthlyDates(FixObject $fix, Carbon $from, Carbon $to): array
    {
        $targetDay = $fix->valid_from->day;

        // Clamp to actual days in this month
        $daysInMonth  = $from->daysInMonth;
        $actualDay    = min($targetDay, $daysInMonth);
        $occurrenceDate = Carbon::create($from->year, $from->month, $actualDay)->startOfDay();

        if ($occurrenceDate->between($from, $to)) {
            return [$occurrenceDate];
        }

        return [];
    }
}
