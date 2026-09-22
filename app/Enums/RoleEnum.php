<?php

namespace App\Enums;

enum RoleEnum: string
{
    case Administrator = 'administrator';
    case Vorarbeiter   = 'vorarbeiter';
    case Mitarbeiter   = 'mitarbeiter';

    /**
     * Human-readable label (used in views & API responses).
     */
    public function label(): string
    {
        return match($this) {
            RoleEnum::Administrator => 'Administrator',
            RoleEnum::Vorarbeiter   => 'Vorarbeiter',
            RoleEnum::Mitarbeiter   => 'Mitarbeiter',
        };
    }

    /**
     * Whether this role accesses the web admin dashboard.
     */
    public function isAdminRole(): bool
    {
        return $this === RoleEnum::Administrator;
    }

    /**
     * Whether this role uses the mobile API (token-based).
     */
    public function isApiRole(): bool
    {
        return in_array($this, [RoleEnum::Vorarbeiter, RoleEnum::Mitarbeiter]);
    }

    /**
     * All role values as a plain array (useful for validation rules).
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
