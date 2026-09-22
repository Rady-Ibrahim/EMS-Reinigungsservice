<?php

namespace App\Enums;

enum AdjustmentStatusEnum: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::Pending  => 'Ausstehend',
            self::Approved => 'Genehmigt',
            self::Rejected => 'Abgelehnt',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
