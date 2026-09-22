<?php

namespace App\Enums;

enum CustomerStatusEnum: string
{
    case Active   = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match($this) {
            self::Active   => 'Aktiv',
            self::Inactive => 'Inaktiv',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
