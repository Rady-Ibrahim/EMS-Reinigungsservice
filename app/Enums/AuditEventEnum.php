<?php

namespace App\Enums;

enum AuditEventEnum: string
{
    case Created         = 'created';
    case Updated         = 'updated';
    case Deleted         = 'deleted';
    case Restored        = 'restored';
    case Reopened        = 'reopened';
    case Reassigned      = 'reassigned';
    case ForceOverride   = 'force_override';
    case SecurityChanged = 'security_changed';
    case HoursAdjusted   = 'hours_adjusted';
    case ReportApproved  = 'report_approved';

    public function label(): string
    {
        return match($this) {
            self::Created         => 'Erstellt',
            self::Updated         => 'Aktualisiert',
            self::Deleted         => 'Gelöscht',
            self::Restored        => 'Wiederhergestellt',
            self::Reopened        => 'Wiedereröffnet',
            self::Reassigned      => 'Umgewiesen',
            self::ForceOverride   => 'Doppelbuchung forciert',
            self::SecurityChanged => 'Sicherheit',
            self::HoursAdjusted   => 'Stunden korrigiert',
            self::ReportApproved  => 'Bericht freigegeben',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}