<?php

namespace App\Enums;

enum InternalEventTypeEnum: string
{
    case Inspection = 'inspection';
    case Meeting    = 'meeting';
    case Call       = 'call';
    case Reminder   = 'reminder';
    case Other      = 'other';

    public function label(): string
    {
        return match($this) {
            self::Inspection => 'Begehung',
            self::Meeting    => 'Besprechung',
            self::Call       => 'Anruf',
            self::Reminder   => 'Erinnerung',
            self::Other      => 'Sonstiges',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
