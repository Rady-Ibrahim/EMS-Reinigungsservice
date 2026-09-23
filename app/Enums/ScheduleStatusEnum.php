<?php

namespace App\Enums;

enum ScheduleStatusEnum: string
{
    case Pending    = 'pending';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Skipped    = 'skipped';

    public function label(): string
    {
        return match($this) {
            self::Pending    => 'Ausstehend',
            self::InProgress => 'In Bearbeitung',
            self::Completed  => 'Abgeschlossen',
            self::Skipped    => 'Übersprungen',
        };
    }

    /** Terminal states — no further transitions allowed. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Skipped]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
