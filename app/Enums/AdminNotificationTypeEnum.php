<?php

namespace App\Enums;

enum AdminNotificationTypeEnum: string
{
    case Conflict = 'conflict';
    case Teamup   = 'teamup';
    case System   = 'system';
    case Alert    = 'alert';

    public function label(): string
    {
        return match($this) {
            self::Conflict => 'Terminkonflikt',
            self::Teamup   => 'Teamup-Sync',
            self::System   => 'System',
            self::Alert    => 'Alarm',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Conflict => 'badge-red',
            self::Teamup   => 'badge-yellow',
            self::System   => 'badge-gray',
            self::Alert    => 'badge-red',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
