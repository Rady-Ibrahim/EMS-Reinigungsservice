<?php

namespace App\Enums;

enum AssigneeRoleEnum: string
{
    case Leader = 'leader';   // Vorarbeiter — responsible for closing order
    case Member = 'member';   // Mitarbeiter — executes work, records own time

    public function label(): string
    {
        return match($this) {
            self::Leader => 'Vorarbeiter (Verantwortlich)',
            self::Member => 'Mitarbeiter',
        };
    }

    public function isLeader(): bool
    {
        return $this === self::Leader;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
