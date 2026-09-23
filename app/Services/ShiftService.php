<?php

namespace App\Services;

use App\Models\EmployeeShift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function __construct(
        private readonly ConflictCheckerService $conflicts,
        private readonly NotificationEngineService $notifications
    ) {
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

        $shift = EmployeeShift::create($data + ['created_by' => auth()->id()]);

        $this->notifyUser($shift->user_id, \App\Enums\NotificationTypeEnum::ShiftCreated,
            'Neue Schicht',
            'Eine neue Schicht wurde für dich geplant: '.$start->format('d.m.Y H:i').' – '.$end->format('H:i'),
            ['type' => 'shift', 'shift_id' => $shift->id]
        );

        return $shift;
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

        $oldUserId = $shift->user_id;

        $shift->update($data);

        $fresh = $shift->fresh();

        if ((int) ($data['user_id'] ?? $oldUserId) !== (int) $oldUserId) {
            // Employee swapped → new employee gets a shift notification
            $this->notifyUser($fresh->user_id, \App\Enums\NotificationTypeEnum::ShiftCreated,
                'Schicht übernommen',
                'Dir wurde eine Schicht zugeteilt: '.$start->format('d.m.Y H:i'),
                ['type' => 'shift', 'shift_id' => $fresh->id]
            );
        } elseif (isset($data['start_at']) || isset($data['end_at'])) {
            $this->notifyUser($fresh->user_id, \App\Enums\NotificationTypeEnum::ScheduleChanged,
                'Schicht geändert',
                'Deine Schicht wurde geändert: '.$start->format('d.m.Y H:i').' – '.$end->format('H:i'),
                ['type' => 'shift', 'shift_id' => $fresh->id]
            );
        }

        return $fresh;
    }

    public function delete(EmployeeShift $shift): void
    {
        $shift->delete();
    }

    public function cancel(EmployeeShift $shift): EmployeeShift
    {
        $shift->update(['status' => \App\Enums\ShiftStatusEnum::Cancelled]);

        $this->notifyUser($shift->user_id, \App\Enums\NotificationTypeEnum::ShiftCancelled,
            'Schicht storniert',
            'Deine Schicht am '.$shift->start_at->format('d.m.Y H:i').' wurde storniert.',
            ['type' => 'shift', 'shift_id' => $shift->id]
        );

        return $shift->fresh();
    }

    private function notifyUser(int $userId, \App\Enums\NotificationTypeEnum $type, string $title, string $message, array $payload): void
    {
        $user = \App\Models\User::find($userId);

        if ($user && $user->is_active) {
            $this->notifications->sendToUser($user, $type, $title, $message, $payload);
        }
    }
}