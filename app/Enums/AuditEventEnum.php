<?php

namespace App\Enums;

enum AuditEventEnum: string
{
    case Created  = 'created';
    case Updated  = 'updated';
    case Deleted  = 'deleted';
    case Restored = 'restored';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
