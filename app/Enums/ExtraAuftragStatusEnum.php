<?php

namespace App\Enums;

enum ExtraAuftragStatusEnum: string
{
    case Pending     = 'pending';
    case Assigned    = 'assigned';
    case InProgress  = 'in_progress';
    case Completed   = 'completed';
    case Cancelled   = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending    => 'Ausstehend',
            self::Assigned   => 'Zugewiesen',
            self::InProgress => 'In Bearbeitung',
            self::Completed  => 'Abgeschlossen',
            self::Cancelled  => 'Storniert',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled]);
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Pending    => 'badge-gray',
            self::Assigned   => 'badge-blue',
            self::InProgress => 'badge-yellow',
            self::Completed  => 'badge-green',
            self::Cancelled  => 'badge-red',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
