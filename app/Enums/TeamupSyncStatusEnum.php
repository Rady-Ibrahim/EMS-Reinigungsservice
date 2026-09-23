<?php

namespace App\Enums;

enum TeamupSyncStatusEnum: string
{
    case Pending = 'pending';
    case Synced  = 'synced';
    case Failed  = 'failed';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Ausstehend',
            self::Synced  => 'Synchronisiert',
            self::Failed  => 'Fehlgeschlagen',
            self::Deleted => 'Gelöscht',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
