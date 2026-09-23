<?php

namespace App\Enums;

enum ExecutionStatusEnum: string
{
    case Started      = 'started';
    case PhotosBefore = 'photos_before';
    case Cleaning     = 'cleaning';
    case PhotosAfter  = 'photos_after';
    case Completed    = 'completed';

    public function label(): string
    {
        return match($this) {
            self::Started      => 'Auftrag gestartet',
            self::PhotosBefore => 'Vorher-Fotos',
            self::Cleaning     => 'Reinigung',
            self::PhotosAfter  => 'Nachher-Fotos',
            self::Completed    => 'Abgeschlossen',
        };
    }

    /**
     * Allowed next statuses — enforces strict linear workflow.
     * @return ExecutionStatusEnum[]
     */
    public function allowedNextStatuses(): array
    {
        return match($this) {
            self::Started      => [self::PhotosBefore],
            self::PhotosBefore => [self::Cleaning],
            self::Cleaning     => [self::PhotosAfter],
            self::PhotosAfter  => [self::Completed],
            self::Completed    => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStatuses());
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
