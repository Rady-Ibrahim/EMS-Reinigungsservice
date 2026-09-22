<?php

namespace App\Enums;

enum ContractTypeEnum: string
{
    case Vollzeit  = 'vollzeit';
    case Teilzeit  = 'teilzeit';
    case Minijob   = 'minijob';

    public function label(): string
    {
        return match($this) {
            self::Vollzeit => 'Vollzeit',
            self::Teilzeit => 'Teilzeit',
            self::Minijob  => 'Minijob',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
