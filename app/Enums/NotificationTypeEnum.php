<?php

namespace App\Enums;

enum NotificationTypeEnum: string
{
    case JobAssigned   = 'job_assigned';
    case JobUpdated    = 'job_updated';
    case JobCancelled  = 'job_cancelled';
    case JobReopened   = 'job_reopened';
    case ScheduleChanged = 'schedule_changed';
    case Reassigned    = 'reassigned';
    case ShiftCreated  = 'shift_created';
    case ShiftCancelled = 'shift_cancelled';
    case Alert         = 'alert';
    case System        = 'system';

    public function label(): string
    {
        return match($this) {
            self::JobAssigned    => 'Auftrag zugewiesen',
            self::JobUpdated     => 'Auftrag geändert',
            self::JobCancelled   => 'Auftrag storniert',
            self::JobReopened    => 'Auftrag wiedereröffnet',
            self::ScheduleChanged => 'Zeitplan geändert',
            self::Reassigned     => 'Umgewiesen',
            self::ShiftCreated   => 'Schicht erstellt',
            self::ShiftCancelled => 'Schicht storniert',
            self::Alert          => 'Warnung',
            self::System         => 'System',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}