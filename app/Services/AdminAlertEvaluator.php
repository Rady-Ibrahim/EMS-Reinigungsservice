<?php

namespace App\Services;

use App\Enums\AdminNotificationTypeEnum;
use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\ExecutionStatusEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObjectExecution;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Smart admin alerts — evaluated by the `ems:admin-alerts` command.
 *
 * Scheduled evaluations:
 *  - Hourly:      `photos_missing` (execution stuck at after-photos or closed
 *                 without photos) and `unclosed` (extra orders overdue).
 *  - Daily 02:00: `monthly_hours` (actual paid vs contract hours per employee).
 *
 * Idempotent: every alert carries a `dedupe_key` and is suppressed if the
 * same key fired within the last 24h.
 */
class AdminAlertEvaluator
{
    public function __construct(
        private readonly NotificationEngineService $notifications,
        private readonly TimeTrackingEngine $timeTracking,
        private readonly ContractHoursCalculator $contractHours
    ) {
    }

    // ── Hourly ─────────────────────────────────────────────────────────────

    /**
     * Extra: leader stuck at PhotosAfter without after photos.
     * Extra: orders completed while after photos were never recorded.
     * Fix:   completed executions without after photos.
     *
     * @return int  number of alerts created
     */
    public function evaluateMissingPhotos(): int
    {
        $created = 0;

        $extraExecutions = ExtraAuftragExecution::whereIn('status', [
                ExtraExecutionStatusEnum::PhotosAfter,
                ExtraExecutionStatusEnum::Completed,
            ])
            ->with(['extraAuftrag:id,title', 'employee:id,name'])
            ->get();

        foreach ($extraExecutions as $execution) {
            if (! empty($execution->after_photos)) {
                continue;
            }

            $order = $execution->extraAuftrag;
            $stage = $execution->status === ExtraExecutionStatusEnum::Completed
                ? 'abgeschlossen ohne Nachher-Fotos'
                : 'wartet auf Nachher-Fotos';

            if ($this->alert(
                'photos_missing:extra_exec:'.$execution->id,
                "Nachher-Fotos fehlen: Auftrag #{$order->id}",
                "\"{$order->title}\" wurde {$stage}. Mitarbeiter: {$execution->employee->name}.",
                ['type' => 'extra', 'auftrag_id' => $order->id, 'execution_id' => $execution->id]
            )) {
                $created++;
            }
        }

        $fixExecutions = FixObjectExecution::where('status', ExecutionStatusEnum::Completed)
            ->with(['schedule:id,scheduled_date,fix_object_id', 'employee:id,name'])
            ->get();

        foreach ($fixExecutions as $execution) {
            if (! empty($execution->after_photos)) {
                continue;
            }

            if ($this->alert(
                'photos_missing:fix_exec:'.$execution->id,
                "Nachher-Fotos fehlen: Termin {$execution->schedule?->scheduled_date->format('d.m.Y')}",
                "Das Objekt-Protokoll wurde ohne Nachher-Fotos abgeschlossen. Mitarbeiter: {$execution->employee->name}.",
                ['type' => 'fix', 'schedule_id' => $execution->schedule_id, 'execution_id' => $execution->id]
            )) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Extra-Aufträge still open after their scheduled date.
     *
     * @return int  number of alerts created
     */
    public function evaluateUnclosed(?CarbonInterface $asOf = null): int
    {
        $now = $asOf ?? now();
        $created = 0;

        $orders = \App\Models\ExtraAuftrag::whereIn('status', [
                ExtraAuftragStatusEnum::Assigned,
                ExtraAuftragStatusEnum::InProgress,
            ])
            ->whereDate('scheduled_date', '<', $now->toDateString())
            ->get();

        foreach ($orders as $order) {
            $overdueDays = max(1, (int) $order->scheduled_date->diffInDays($now->copy()->startOfDay()));

            if ($this->alert(
                'unclosed:extra:'.$order->id,
                "Überfälliger Auftrag: #{$order->id}",
                "\"{$order->title}\" ({$order->scheduled_date->format('d.m.Y')}) ist seit {$overdueDays} Tag(en) nicht abgeschlossen.",
                ['type' => 'extra', 'auftrag_id' => $order->id]
            )) {
                $created++;
            }
        }

        return $created;
    }

    // ── Daily 02:00 ────────────────────────────────────────────────────────

    /**
     * Compare actual paid hours against contract hours for each active
     * employee for the given month (defaults to the previous month).
     *
     * @return int  number of alerts created
     */
    public function evaluateMonthlyHours(?CarbonInterface $forMonth = null): int
    {
        $month = ($forMonth ?? now()->subMonthNoOverflow())->copy()->startOfMonth();
        $year      = (int) $month->format('Y');
        $monthNum  = (int) $month->format('n');
        $threshold = (float) config('alerts.min_monthly_hours_percent', 0.8);

        $employees = User::whereIn('role', ['vorarbeiter', 'mitarbeiter'])
            ->where('is_active', true)
            ->get();

        $created = 0;

        foreach ($employees as $employee) {
            $planned = $this->plannedMonthlyHours($employee, $year, $monthNum);

            if ($planned <= 0) {
                continue;
            }

            $actual = $this->timeTracking
                ->monthlyTotalsForEmployee($employee, $year, $monthNum)['total_paid_hours'];

            if ($actual >= $planned * $threshold) {
                continue;
            }

            $key = 'monthly_hours:'.$employee->id.':'.$year.'-'.$monthNum;

            $hoursShort = round($planned - $actual, 2);

            if (! $this->alert(
                $key,
                "Unterbuchung: {$employee->name}",
                "Im {$month->format('m/Y')} wurden {$actual}h statt geplanter {$planned}h gebucht (Fehlstand {$hoursShort}h).",
                ['type' => 'monthly_hours', 'user_id' => $employee->id, 'year' => $year, 'month' => $monthNum]
            )) {
                continue;
            }

            $created++;

            $this->notifications->sendToUser(
                $employee,
                NotificationTypeEnum::Alert,
                'Unterbuchung '.$month->format('m/Y'),
                "Gebucht: {$actual}h von geplanten {$planned}h (Fehlstand {$hoursShort}h).",
                ['type' => 'monthly_hours', 'year' => $year, 'month' => $monthNum]
            );
        }

        return $created;
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function plannedMonthlyHours(User $employee, int $year, int $month): float
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd   = $monthStart->copy()->endOfMonth();

        $planned = 0.0;

        $assignments = \App\Models\FixObjectAssignment::forEmployee($employee->id)
            ->with('fixObject')
            ->get();

        foreach ($assignments as $assignment) {
            if ($assignment->isActiveOn($monthStart) || $assignment->isActiveOn($monthEnd)) {
                $planned += $this->contractHours->monthlyHours($assignment->fixObject, $year, $month);
            }
        }

        return round($planned, 2);
    }

    /**
     * Deduplicated admin-inbox alert.
     *
     * @return bool  true if a new alert was created
     */
    private function alert(string $dedupeKey, string $title, string $message, array $payload): bool
    {
        if ($this->alreadySent($dedupeKey)) {
            return false;
        }

        $this->notifications->notifyAdmins(
            AdminNotificationTypeEnum::Alert,
            $title,
            $message,
            $payload,
            $dedupeKey
        );

        return true;
    }

    private function alreadySent(string $key): bool
    {
        return \App\Models\AdminNotification::where('dedupe_key', $key)
            ->where('created_at', '>', now()->subHours(24))
            ->exists();
    }
}