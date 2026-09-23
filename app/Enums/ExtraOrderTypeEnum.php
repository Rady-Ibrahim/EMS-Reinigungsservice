<?php

namespace App\Enums;

enum ExtraOrderTypeEnum: string
{
    case DeepClean        = 'deep_clean';
    case Window           = 'window';
    case PostConstruction = 'post_construction';
    case Emergency        = 'emergency';
    case Other            = 'other';

    public function label(): string
    {
        return match($this) {
            self::DeepClean        => 'Grundreinigung',
            self::Window           => 'Fensterreinigung',
            self::PostConstruction => 'Baureinigung',
            self::Emergency        => 'Notfalleinsatz',
            self::Other            => 'Sonstiges',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
