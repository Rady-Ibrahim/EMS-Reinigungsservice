<?php

namespace App\Enums;

enum CalendarEventTypeEnum: string
{
    case FixSchedule         = 'fix_schedule';
    case ExtraAuftrag        = 'extra_auftrag';
    case EmployeeShift       = 'employee_shift';
    case PersonalAppointment = 'personal_appointment';
    case InternalEvent       = 'internal_event';
    case TeamupExternal      = 'teamup_external';

    public function label(): string
    {
        return match($this) {
            self::FixSchedule         => 'Fixobjekt-Termin',
            self::ExtraAuftrag        => 'Extra-Auftrag',
            self::EmployeeShift       => 'Schicht',
            self::PersonalAppointment => 'Persönlicher Termin',
            self::InternalEvent       => 'Interner Termin',
            self::TeamupExternal      => 'Teamup (extern)',
        };
    }

    /** Toggle layer this event type belongs to in the unified calendar. */
    public function layer(): string
    {
        return match($this) {
            self::FixSchedule, self::ExtraAuftrag => 'jobs',
            self::EmployeeShift                   => 'shifts',
            self::PersonalAppointment             => 'personal',
            self::InternalEvent                   => 'internal',
            self::TeamupExternal                  => 'teamup',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
