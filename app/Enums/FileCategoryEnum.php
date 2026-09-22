<?php

namespace App\Enums;

enum FileCategoryEnum: string
{
    case FloorPlan    = 'floor_plan';
    case Instructions = 'instructions';
    case Contract     = 'contract';
    case Photo        = 'photo';
    case Other        = 'other';

    public function label(): string
    {
        return match($this) {
            self::FloorPlan    => 'Grundriss',
            self::Instructions => 'Anweisungen',
            self::Contract     => 'Vertrag',
            self::Photo        => 'Foto',
            self::Other        => 'Sonstiges',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
