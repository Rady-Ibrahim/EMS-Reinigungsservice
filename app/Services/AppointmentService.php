<?php

namespace App\Services;

use App\Models\PersonalAppointment;
use Illuminate\Support\Carbon;

/**
 * Personal appointments (Meine Termine) — a private, toggleable layer.
 * They are NOT work orders and never produce paid hours.
 */
class AppointmentService
{
    public function __construct(private readonly ConflictCheckerService $conflicts)
    {
    }

    public function create(array $data, bool $force = false): PersonalAppointment
    {
        $start = Carbon::parse($data['start_at']);
        $end   = Carbon::parse($data['end_at']);

        $this->conflicts->assertClean(
            $this->conflicts->findWindowConflicts(
                [(int) $data['user_id']],
                $start,
                $end,
                (bool) ($data['all_day'] ?? false)
            ),
            $force
        );

        return PersonalAppointment::create($data);
    }

    public function update(PersonalAppointment $appointment, array $data, bool $force = false): PersonalAppointment
    {
        $start = Carbon::parse($data['start_at'] ?? $appointment->start_at);
        $end   = Carbon::parse($data['end_at'] ?? $appointment->end_at);

        $this->conflicts->assertClean(
            $this->conflicts->findWindowConflicts(
                [(int) ($data['user_id'] ?? $appointment->user_id)],
                $start,
                $end,
                (bool) ($data['all_day'] ?? $appointment->all_day),
                ['personal_appointment:'.$appointment->id]
            ),
            $force
        );

        $appointment->update($data);

        return $appointment->fresh();
    }

    public function delete(PersonalAppointment $appointment): void
    {
        $appointment->delete();
    }
}