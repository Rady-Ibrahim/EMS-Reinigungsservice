<?php

namespace App\Enums;

enum FileVisibilityEnum: string
{
    case Intern = 'intern';   // Admin-only
    case Extern = 'extern';   // Visible to workers / future customer portal

    public function label(): string
    {
        return match($this) {
            self::Intern => 'Intern',
            self::Extern => 'Extern',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
