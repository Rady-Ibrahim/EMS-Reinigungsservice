<?php

namespace App\Enums;

enum ComplaintStatusEnum: string
{
    case Pending    = 'pending';
    case InProgress = 'in_progress';
    case Resolved   = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Offen',
            self::InProgress => 'In Bearbeitung',
            self::Resolved   => 'Erledigt',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending    => 'badge-red',
            self::InProgress => 'badge-yellow',
            self::Resolved   => 'badge-green',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
