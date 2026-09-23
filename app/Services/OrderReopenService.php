<?php

namespace App\Services;

use App\Enums\AuditEventEnum;
use App\Enums\ExecutionStatusEnum;
use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\ScheduleStatusEnum;
use App\Models\ExtraAuftrag;
use App\Models\FixObjectSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reopens closed work orders with a mandatory governance reason.
 *
 *  - Extra-Auftrag: whole order → status `assigned`, all completed executions
 *    reset to `working` (work_end cleared so paid minutes are recomputed on
 *    re-close). Travel tracks stay frozen.
 *  - Fixobjekt: single schedule → status `in_progress`, its most recent
 *    execution reset to `photos_after` (actual_end cleared).
 *
 * Every reopen is written to the audit log with the reason.
 */
class OrderReopenService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationEngineService $notifications
    ) {
    }

    public function reopenExtra(ExtraAuftrag $order, User $admin, string $reason): ExtraAuftrag
    {
        if (! $order->status->isTerminal() || $order->status === ExtraAuftragStatusEnum::Cancelled) {
            throw ValidationException::withMessages([
                'reopen' => 'Nur abgeschlossene Aufträge können wieder geöffnet werden.',
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Ein Grund für die Wiedereröffnung ist Pflicht.',
            ]);
        }

        return DB::transaction(function () use ($order, $admin, $reason) {
            foreach ($order->executions as $execution) {
                if (! $execution->isCompleted()) {
                    continue;
                }

                $execution->update([
                    'status'   => ExtraExecutionStatusEnum::Working,
                    'work_end' => null,
                ]);
            }

            $order->update(['status' => ExtraAuftragStatusEnum::Assigned]);

            $this->audit->record(
                $order,
                AuditEventEnum::Reopened,
                oldValues: ['status' => ExtraAuftragStatusEnum::Completed->value],
                newValues: ['status' => ExtraAuftragStatusEnum::Assigned->value],
                reason: $reason,
                actorId: $admin->id,
            );

            $this->notifications->sendToUser(
                $admin,
                NotificationTypeEnum::JobReopened,
                "Auftrag #{$order->id} wieder geöffnet",
                "Der Auftrag \"{$order->title}\" wurde wieder geöffnet.",
                ['type' => 'extra', 'auftrag_id' => $order->id]
            );

            return $order->fresh(['executions', 'assignees.user']);
        });
    }

    public function reopenFix(FixObjectSchedule $schedule, User $admin, string $reason): FixObjectSchedule
    {
        if (! $schedule->isCompleted()) {
            throw ValidationException::withMessages([
                'reopen' => 'Nur abgeschlossene Termine können wieder geöffnet werden.',
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Ein Grund für die Wiedereröffnung ist Pflicht.',
            ]);
        }

        return DB::transaction(function () use ($schedule, $admin, $reason) {
            $execution = $schedule->executions()->latest('id')->first();

            if ($execution && $execution->isCompleted()) {
                $execution->update([
                    'status'     => ExecutionStatusEnum::PhotosAfter,
                    'actual_end' => null,
                ]);
            }

            $schedule->update(['status' => ScheduleStatusEnum::InProgress]);

            $this->audit->record(
                $schedule,
                AuditEventEnum::Reopened,
                oldValues: ['status' => ScheduleStatusEnum::Completed->value],
                newValues: ['status' => ScheduleStatusEnum::InProgress->value],
                reason: $reason,
                actorId: $admin->id,
            );

            $this->notifications->sendToUser(
                $admin,
                NotificationTypeEnum::JobReopened,
                "Termin vom {$schedule->scheduled_date->format('d.m.Y')} wieder geöffnet",
                "Der Termin im Objekt läuft wieder.",
                ['type' => 'fix', 'schedule_id' => $schedule->id]
            );

            return $schedule->fresh(['executions']);
        });
    }
}