<?php

namespace App\Enums;

enum ExtraExecutionStatusEnum: string
{
    case Travelling  = 'travelling';   // Anfahrt gestartet
    case Arrived     = 'arrived';      // Am Standort angekommen
    case Working     = 'working';      // Arbeit begonnen
    case PhotosAfter = 'photos_after'; // Nachher-Fotos (Leader only)
    case Completed   = 'completed';    // Auftrag beendet (Leader only)

    public function label(): string
    {
        return match($this) {
            self::Travelling  => 'Auf dem Weg',
            self::Arrived     => 'Angekommen',
            self::Working     => 'In Arbeit',
            self::PhotosAfter => 'Nachher-Fotos',
            self::Completed   => 'Abgeschlossen',
        };
    }

    /**
     * Allowed next statuses for any employee (member or leader).
     * Leader-only transitions are validated separately in the service layer.
     *
     * @return ExtraExecutionStatusEnum[]
     */
    public function allowedNextStatuses(): array
    {
        return match($this) {
            self::Travelling  => [self::Arrived],
            self::Arrived     => [self::Working],
            self::Working     => [self::PhotosAfter],
            self::PhotosAfter => [self::Completed],
            self::Completed   => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStatuses());
    }

    /** States that require leader privilege to enter */
    public function requiresLeader(): bool
    {
        return in_array($this, [self::PhotosAfter, self::Completed]);
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
