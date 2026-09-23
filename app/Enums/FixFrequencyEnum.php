<?php

namespace App\Enums;

enum FixFrequencyEnum: string
{
    case Daily      = 'daily';       // täglich
    case Weekly     = 'weekly';      // 1x pro Woche
    case Biweekly   = 'biweekly';    // 2x pro Woche
    case Triweekly  = 'triweekly';   // 3x pro Woche
    case Monthly    = 'monthly';     // 1x pro Monat

    public function label(): string
    {
        return match($this) {
            self::Daily     => 'Täglich',
            self::Weekly    => '1x pro Woche',
            self::Biweekly  => '2x pro Woche',
            self::Triweekly => '3x pro Woche',
            self::Monthly   => '1x pro Monat',
        };
    }

    /**
     * Expected occurrences per standard month (4.33 weeks).
     * Used for financial estimations only — actual count comes from ScheduleGenerator.
     */
    public function estimatedMonthlyOccurrences(): float
    {
        return match($this) {
            self::Daily     => 21.7,   // workdays only estimate
            self::Weekly    => 4.33,
            self::Biweekly  => 8.66,
            self::Triweekly => 13.0,
            self::Monthly   => 1.0,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
