<?php

namespace App\Services;

use App\Models\EmployeeShift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function __construct(private readonly ConflictCheckerService $conflicts)
    {
    }

    public function create(array $data, bool $force = false): EmployeeShift
    {
        $start = Carbon::parse($data['start_at']);
        $end   = Carbon::parse($data['end_at']);
        $allDay = (bool) ($data['all_day'] ?? false);

        $this->conflicts->assertClean(
            $this->conflicts->findWindowConflicts(
                [(int) $data['user_id']],
                $start,
                $end,
                $allDay
            ),
            $force
        );

        return EmployeeShift::create($data + ['created_by' => auth()->id()]);
    }

    public function update(EmployeeShift $shift, array $data, bool $force = false): EmployeeShift
    {
        $start = Carbon::parse($data['start_at'] ?? $shift->start_at);
        $end   = Carbon::parse($data['end_at'] ?? $shift->end_at);
        $allDay = (bool) ($data['all_day'] ?? $shift->all_day);

        $this->conflicts->assertClean(
            $this->conflicts->findWindowConflicts(
                [(int) ($data['user_id'] ?? $shift->user_id)],
                $start,
                $end,
                $allDay,
                ['employee_shift:'.$shift->id]
            ),
            $force
        );

        $shift->update($data);

        return $shift->fresh();
    }

    public function delete(EmployeeShift $shift): void
    {
        $shift->delete();
    }

    public function cancel(EmployeeShift $shift): EmployeeShift
    {
        $shift->update(['status' => \App\Enums\ShiftStatusEnum::Cancelled]);

        return $shift->fresh();
    }
}