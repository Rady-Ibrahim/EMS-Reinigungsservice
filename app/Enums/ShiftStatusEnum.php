<?php

namespace App\Enums;

enum ShiftStatusEnum: string
{
    case Planned   = 'planned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Planned   => 'Geplant',
            self::Completed => 'Abgeschlossen',
            self::Cancelled => 'Storniert',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Cancelled || $this === self::Completed;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
