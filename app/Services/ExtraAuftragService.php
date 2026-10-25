<?php

namespace App\Services;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Models\AdminCloseAttempt;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragAssignee;
use App\Models\ExtraAuftragExecution;
use App\Models\TravelTrack;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExtraAuftragService
{
    public function __construct(
        private readonly TravelTimeCalculatorService $travelCalculator,
        private readonly ConflictCheckerService $conflicts
    ) {
    }

    // ── CRUD ───────────────────────────────────────────────────────────────

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return ExtraAuftrag::with([
            'customer:id,name',
            'location:id,name,city',
            'leader.user:id,name',
        ])
        ->withCount('assignees')
        ->orderBy('scheduled_date', 'desc')
        ->paginate($perPage);
    }

    /**
     * Create a new Extra-Auftrag with its team assignments.
     * Validates that exactly one leader is assigned.
     *
     * @param  array  $data           — validated order fields
     * @param  array  $assignees      — [['user_id' => X, 'role_in_order' => 'leader'|'member'], ...]
     */
    public function create(array $data, array $assignees, bool $force = false): ExtraAuftrag
    {
        $this->validateLeaderPresent($assignees);

        $employeeIds = $this->uniqueAssignees($assignees);
        $this->assertNoEmployeeConflicts($data, $employeeIds, [], $force);

        return DB::transaction(function () use ($data, $assignees) {
            $order = ExtraAuftrag::create($data);
            $this->syncAssignees($order, $assignees);
            $order->update(['status' => ExtraAuftragStatusEnum::Assigned]);
            return $order->load(['customer', 'location', 'assignees.user']);
        });
    }

    public function update(ExtraAuftrag $order, array $data, ?array $assignees = null, bool $force = false): ExtraAuftrag
    {
        $employeeIds = $assignees !== null
            ? $this->uniqueAssignees($assignees)
            : $order->assignees()->pluck('user_id')->all();

        $this->assertNoEmployeeConflicts($data, $employeeIds, ['extra_auftrag:'.$order->id], $force);

        return DB::transaction(function () use ($order, $data, $assignees) {
            if ($assignees !== null) {
                $this->validateLeaderPresent($assignees);
                $this->syncAssignees($order, $assignees);
            }

            $order->update($data);
            return $order->fresh(['customer', 'location', 'assignees.user']);
        });
    }

    public function cancel(ExtraAuftrag $order, string $reason): ExtraAuftrag
    {
        if ($order->status->isTerminal()) {
            throw new \RuntimeException('Cannot cancel a terminal order.');
        }

        $order->update([
            'status'           => ExtraAuftragStatusEnum::Cancelled,
            'cancelled_reason' => $reason,
        ]);

        return $order->fresh();
    }

    // ── Travel Workflow ────────────────────────────────────────────────────

    /**
     * Employee starts travel to the job site (Anfahrt starten).
     */
    public function startTravel(ExtraAuftrag $order, int $userId, array $data): TravelTrack
    {
        $this->assertAssigned($order, $userId);

        // Ensure employee has an execution record in 'travelling' state
        $this->ensureExecution($order, $userId);

        return TravelTrack::create([
            'extra_auftrag_id'   => $order->id,
            'user_id'            => $userId,
            'departure_at'       => $data['departure_at'] ?? now(),
            'gps_departure_lat'  => $data['gps_departure_lat'] ?? null,
            'gps_departure_lng'  => $data['gps_departure_lng'] ?? null,
            'is_paid'            => false, // frozen to false until arrival
            'offline_uuid'       => $data['offline_uuid'] ?? null,
        ]);
    }

    /**
     * Employee arrives at the job site (Ankunft).
     * Freezes travel_minutes and is_paid from order settings.
     */
    public function recordArrival(ExtraAuftrag $order, int $userId, array $data): TravelTrack
    {
        $this->assertAssigned($order, $userId);

        $track = TravelTrack::where('extra_auftrag_id', $order->id)
                             ->where('user_id', $userId)
                             ->firstOrFail();

        if ($track->hasArrived()) {
            throw new \RuntimeException('Arrival already recorded.');
        }

        $arrivalAt      = $data['arrival_at'] ?? now();
        $travelMinutes  = $this->travelCalculator->calculateTravelMinutes(
            $track->fill(['arrival_at' => $arrivalAt])
        );

        $track->update([
            'arrival_at'       => $arrivalAt,
            'gps_arrival_lat'  => $data['gps_arrival_lat'] ?? null,
            'gps_arrival_lng'  => $data['gps_arrival_lng'] ?? null,
            'travel_minutes'   => $travelMinutes,
            // Freeze is_paid from order at this exact moment
            'is_paid'          => $order->is_travel_time_paid,
        ]);

        // Advance execution status to 'arrived'
        $execution = $this->ensureExecution($order, $userId);
        if ($execution->status === ExtraExecutionStatusEnum::Travelling) {
            $execution->transitionTo(ExtraExecutionStatusEnum::Arrived);
        }

        return $track->fresh();
    }

    // ── Work Workflow ──────────────────────────────────────────────────────

    /**
     * Employee starts actual work at the site.
     */
    public function startWork(ExtraAuftrag $order, int $userId, array $data): ExtraAuftragExecution
    {
        $this->assertAssigned($order, $userId);
        $execution = $this->ensureExecution($order, $userId);

        if (! in_array($execution->status, [ExtraExecutionStatusEnum::Arrived, ExtraExecutionStatusEnum::Travelling])) {
            throw new \RuntimeException('Employee must arrive before starting work.');
        }

        $execution->update([
            'status'              => ExtraExecutionStatusEnum::Working,
            'work_start'          => $data['work_start'] ?? now(),
            'gps_work_start_lat'  => $data['gps_work_start_lat'] ?? null,
            'gps_work_start_lng'  => $data['gps_work_start_lng'] ?? null,
        ]);

        // A start far from the site is reported now, not only once the shift is
        // closed. Deduped per order/execution/stage, so re-running is safe.
        $this->flagGeofenceDeviation($order, $execution->fresh());

        // Auto-transition order status
        if ($order->status === ExtraAuftragStatusEnum::Assigned) {
            $order->update(['status' => ExtraAuftragStatusEnum::InProgress]);
        }

        return $execution->fresh();
    }

    /**
     * Leader advances to after-photos stage.
     */
    public function startAfterPhotos(ExtraAuftrag $order, int $userId, array $data): ExtraAuftragExecution
    {
        $this->assertLeader($order, $userId);
        $execution = $this->getLeaderExecution($order, $userId);
        $execution->transitionTo(ExtraExecutionStatusEnum::PhotosAfter);

        if (! empty($data['after_photos'])) {
            $execution->update(['after_photos' => $data['after_photos']]);
        }

        return $execution->fresh();
    }

    /**
     * Leader completes the entire order.
     * Validates: before_photos, after_photos, checklist all done.
     * Freezes work_minutes and paid_minutes for all team members.
     */
    public function completeOrder(ExtraAuftrag $order, int $userId, array $data): ExtraAuftrag
    {
        $this->assertLeader($order, $userId);

        $leaderExecution = $this->getLeaderExecution($order, $userId);

        // Enforce completion requirements
        if (empty($leaderExecution->before_photos)) {
            throw ValidationException::withMessages([
                'before_photos' => 'Vorher-Fotos sind Pflicht vor dem Abschluss.',
            ]);
        }

        if (empty($leaderExecution->after_photos) && empty($data['after_photos'])) {
            throw ValidationException::withMessages([
                'after_photos' => 'Nachher-Fotos sind Pflicht vor dem Abschluss.',
            ]);
        }

        if (! $leaderExecution->isChecklistComplete()) {
            throw ValidationException::withMessages([
                'checklist_items' => 'Alle Checklisten-Punkte müssen abgehakt sein.',
            ]);
        }

        // ── GPS guard: work was started with GPS → closing must also be GPS-verified ──
        if (! empty($leaderExecution->gps_work_start_lat) && empty($data['gps_work_end_lat'])) {
            throw ValidationException::withMessages([
                'gps_work_end_lat' => 'GPS-Position beim Arbeitsende ist Pflicht, da der Einsatz mit GPS gestartet wurde.',
            ]);
        }

        if (! empty($leaderExecution->gps_work_start_lng) && empty($data['gps_work_end_lng'])) {
            throw ValidationException::withMessages([
                'gps_work_end_lng' => 'GPS-Position beim Arbeitsende ist Pflicht, da der Einsatz mit GPS gestartet wurde.',
            ]);
        }

        // ── Time-anomaly guard: frozen work duration must not exceed 2× the estimate ──
        $leaderWorkEnd = $data['work_end'] ?? now();
        $leaderMinutes = $leaderExecution->work_start
            ? (int) $leaderExecution->work_start->diffInMinutes($leaderWorkEnd)
            : 0;
        $estimateMinutes = (int) round(((float) $order->estimated_hours) * 60);

        if ($estimateMinutes > 0 && $leaderMinutes > $estimateMinutes * 2) {
            throw ValidationException::withMessages([
                'work_end' => 'Die gemeldete Arbeitszeit übersteigt das Doppelte der geplanten Stunden deutlich. Bitte Zeitkorrektur beantragen.',
            ]);
        }

        return DB::transaction(function () use ($order, $userId, $leaderExecution, $data) {
            $now = now();

            // Attach after_photos if provided now
            if (! empty($data['after_photos'])) {
                $leaderExecution->update(['after_photos' => $data['after_photos']]);
            }

            // Freeze metrics for ALL team members
            foreach ($order->executions as $execution) {
                if ($execution->isCompleted()) {
                    continue;
                }

                $workEnd     = ($execution->user_id === $userId)
                    ? ($data['work_end'] ?? $now)
                    : $now;

                $workMinutes = $execution->work_start
                    ? (int) $execution->work_start->diffInMinutes($workEnd)
                    : 0;

                $travelTrack = TravelTrack::where('extra_auftrag_id', $order->id)
                                          ->where('user_id', $execution->user_id)
                                          ->first();

                $paidMinutes = $this->travelCalculator->calculatePaidMinutes(
                    $execution->fill(['work_minutes' => $workMinutes]),
                    $travelTrack
                );

                $execution->update([
                    'status'            => ExtraExecutionStatusEnum::Completed,
                    'work_end'          => $workEnd,
                    'work_minutes'      => $workMinutes,
                    'paid_minutes'      => $paidMinutes,
                    'gps_work_end_lat'  => ($execution->user_id === $userId) ? ($data['gps_work_end_lat'] ?? null) : null,
                    'gps_work_end_lng'  => ($execution->user_id === $userId) ? ($data['gps_work_end_lng'] ?? null) : null,
                    'employee_notes'    => ($execution->user_id === $userId) ? ($data['employee_notes'] ?? null) : $execution->employee_notes,
                ]);
            }

            // Complete the order
            $order->update(['status' => ExtraAuftragStatusEnum::Completed]);

            return $order->fresh(['executions', 'assignees.user']);
        });
    }

    // ── Closing & hand-over (Auftrag beenden & Übergabe) ───────────────────

    /**
     * Employee closes their own execution and confirms the site hand-over.
     *
     * The order is NOT completed here — that stays with the leader via
     * completeOrder(). This records the departure of one team member with the
     * three security confirmations, which is what the mobile closing screen
     * collects.
     *
     * Blocked unless the execution has before and after photos, the checklist
     * is complete, and the hand-over confirmations the site actually requires
     * are ticked. Anything missing comes back as a 422 naming the exact field
     * so the app can highlight the right box.
     */
    public function endWork(ExtraAuftrag $order, int $userId, array $data): ExtraAuftragExecution
    {
        $this->assertAssigned($order, $userId);

        $execution = ExtraAuftragExecution::where('extra_auftrag_id', $order->id)
                                         ->where('user_id', $userId)
                                         ->firstOrFail();

        if ($execution->isCompleted()) {
            throw ValidationException::withMessages([
                'execution' => 'Dieser Einsatz ist bereits abgeschlossen.',
            ]);
        }

        if ($execution->work_start === null) {
            throw ValidationException::withMessages([
                'work_start' => 'Die Arbeitszeit wurde noch nicht gestartet.',
            ]);
        }

        // Recorded before the assertion, otherwise a refused close leaves no
        // trace and the admin evaluator never learns about it. The recorder
        // no-ops when nothing is missing.
        $this->recordBlockedCloseAttempt($order, $execution, $data);

        $this->assertClosureRequirements($order, $execution, $data);

        $workEnd = $data['work_end'] ?? now();

        // The break stops counting as work at the moment the employee resumed,
        // so it is deducted from the span and the remaining span becomes the
        // paid working time.
        $elapsedMinutes = $execution->work_start->diffInMinutes($workEnd, false);
        $pauseMinutes   = $execution->accumulatedPauseMinutes();
        $workMinutes    = max(0, $elapsedMinutes - $pauseMinutes);

        $travelTrack = TravelTrack::where('extra_auftrag_id', $order->id)
                                  ->where('user_id', $userId)
                                  ->first();

        $paidMinutes = $this->travelCalculator->calculatePaidMinutes(
            $execution->fill(['work_minutes' => $workMinutes]),
            $travelTrack
        );

        $execution->update([
            'work_end'         => $workEnd,
            'work_minutes'     => $workMinutes,
            'paid_minutes'     => $paidMinutes,
            'pause_minutes'    => $pauseMinutes,
            // A still-running pause is closed by the hand-over.
            'pause_ended_at'   => $execution->isPaused() ? $workEnd : $execution->pause_ended_at,
            'gps_work_end_lat' => $data['gps_work_end_lat'] ?? null,
            'gps_work_end_lng' => $data['gps_work_end_lng'] ?? null,
            'key_returned'     => (bool) $data['key_returned'],
            'windows_closed'   => (bool) $data['windows_closed'],
            'alarm_activated'  => (bool) $data['alarm_activated'],
            'shift_note'       => $data['shift_note'] ?? null,
            // Only overwrite when the closing screen actually sent new ones.
            'after_photos'     => ! empty($data['after_photos'])
                ? array_values($data['after_photos'])
                : $execution->after_photos,
            'employee_notes'   => filled($data['employee_notes'] ?? null)
                ? $data['employee_notes']
                : $execution->employee_notes,
            'status'           => ExtraExecutionStatusEnum::Completed,
        ]);

        $this->flagGeofenceDeviation($order, $execution);

        return $execution->fresh();
    }

    /**
     * Start or stop the unpaid break.
     *
     * The execution status is intentionally left untouched: a break is not a
     * workflow stage, and moving it would make the "must be working" rule
     * ambiguous. Only the timestamps are recorded, and they are subtracted at
     * close.
     */
    public function togglePause(ExtraAuftrag $order, int $userId, array $data): ExtraAuftragExecution
    {
        $this->assertAssigned($order, $userId);

        $execution = ExtraAuftragExecution::where('extra_auftrag_id', $order->id)
                                         ->where('user_id', $userId)
                                         ->firstOrFail();

        if ($execution->isCompleted() || $execution->work_start === null) {
            throw ValidationException::withMessages([
                'pause' => 'Eine Pause ist nur während der laufenden Arbeitszeit möglich.',
            ]);
        }

        $isStart = ($data['action'] ?? 'start') === 'start';

        if ($isStart) {
            if ($execution->isPaused()) {
                throw ValidationException::withMessages([
                    'pause' => 'Die Pause läuft bereits.',
                ]);
            }

            // `pause_ended_at` must be cleared so `isPaused()` recognises the
            // new break; leaving the previous cycle's end in place would make
            // the second break look finished and impossible to stop.
            $execution->update([
                'pause_started_at' => now(),
                'pause_ended_at'   => null,
            ]);

            return $execution->fresh();
        }

        if (! $execution->isPaused()) {
            throw ValidationException::withMessages([
                'pause' => 'Es läuft keine Pause.',
            ]);
        }

        $endedAt = $data['paused_at'] ?? now();

        if ($endedAt->lt($execution->pause_started_at)) {
            throw ValidationException::withMessages([
                'paused_at' => 'Das Pausenende kann nicht vor dem Beginn liegen.',
            ]);
        }

        // Breaks accumulate: an employee may take several over one shift, and
        // `pause_minutes` is the running total that is deducted at close, so
        // the previous cycles must not be overwritten.
        $execution->update([
            'pause_ended_at' => $endedAt,
            'pause_minutes'  => (int) $execution->pause_minutes
                + (int) $execution->pause_started_at->diffInMinutes($endedAt),
        ]);

        return $execution->fresh();
    }

    /**
     * Append a quick field note from the assigned employee.
     */
    public function addNote(ExtraAuftrag $order, int $userId, string $note): ExtraAuftragExecution
    {
        $this->assertAssigned($order, $userId);

        $execution = ExtraAuftragExecution::where('extra_auftrag_id', $order->id)
                                         ->where('user_id', $userId)
                                         ->firstOrFail();

        $execution->appendNote($note);

        return $execution->fresh();
    }

    /**
     * Leave a trace when a close was refused, so the admin evaluator can turn
     * repeated failures into an alert instead of the app silently failing.
     *
     * Dedupe key is intentionally per attempt-and-reason, and the evaluator
     * re-checks whether the gap still exists before alerting.
     */
    private function recordBlockedCloseAttempt(
        ExtraAuftrag $order,
        ExtraAuftragExecution $execution,
        array $data
    ): void {
        $reasons = [];

        if ($order->isLeader($execution->user_id)) {
            if (! $execution->isChecklistComplete()) {
                $reasons[] = 'checklist';
            }

            if (empty($execution->before_photos) || (empty($execution->after_photos) && empty($data['after_photos']))) {
                $reasons[] = 'photos';
            }
        }

        $handOver = [
            'key_returned'    => (bool) ($data['key_returned'] ?? false),
            'windows_closed'  => (bool) ($data['windows_closed'] ?? false),
            'alarm_activated' => (bool) ($data['alarm_activated'] ?? false),
        ];

        if (! $this->securityHandOverSatisfied($order, $handOver)) {
            $reasons[] = 'security';
        }

        if ($reasons === []) {
            return;
        }

        $this->recordCloseAttempt(
            $order->id,
            $execution->user_id,
            $reasons
        );
    }

    /**
     * Persist a close attempt marker.
     *
     * Deduplicated per order, user and reason set, so hammering "close" on a
     * blocked order does not create a row per tap. The evaluator only needs
     * to know that someone tried and what was missing.
     */
    private function recordCloseAttempt(int $orderId, int $userId, array $reasons): void
    {
        AdminCloseAttempt::firstOrCreate(
            ['dedupe_key' => 'close_attempt:'.$orderId.':'.$userId.':'.implode('-', $reasons)],
            [
                'extra_auftrag_id' => $orderId,
                'user_id'          => $userId,
                'reasons'          => $reasons,
            ]
        );
    }

    /**
     * Site security hand-over is satisfied once every confirmation the site
     * actually needs is present in the submitted payload.
     *
     * A site without a key safe has no key to hand back and a site without an
     * alarm has nothing to arm, so those two are only required when the order
     * says the site has them. Closing the windows is always required.
     *
     * @param  array{key_returned: bool, windows_closed: bool, alarm_activated: bool}  $handOver
     */
    private function securityHandOverSatisfied(ExtraAuftrag $order, array $handOver): bool
    {
        if ((bool) $order->has_key_safe && ! $handOver['key_returned']) {
            return false;
        }

        if (! $handOver['windows_closed']) {
            return false;
        }

        if ((bool) $order->has_alarm_system && ! $handOver['alarm_activated']) {
            return false;
        }

        return true;
    }

    /**
     * Enforce the closing preconditions and report every missing item at once
     * instead of failing on the first one, so the app can show a full list.
     */
    private function assertClosureRequirements(
        ExtraAuftrag $order,
        ExtraAuftragExecution $execution,
        array $data
    ): void {
        $errors = [];

        if ($order->isLeader($execution->user_id)) {
            if (empty($execution->before_photos)) {
                $errors['before_photos'] = 'Vorher-Fotos sind Pflicht vor dem Abschluss.';
            }

            if (empty($execution->after_photos) && empty($data['after_photos'])) {
                $errors['after_photos'] = 'Nachher-Fotos sind Pflicht vor dem Abschluss.';
            }

            if (! $execution->isChecklistComplete()) {
                $errors['checklist_items'] = 'Alle Checklisten-Punkte müssen abgehakt sein.';
            }
        }

        // Judge the hand-over on the confirmations just submitted, not on the
        // values already stored on the execution — those are the previous
        // state and would reject a first, valid hand-over every time.
        $handOver = [
            'key_returned'    => (bool) ($data['key_returned'] ?? false),
            'windows_closed'  => (bool) ($data['windows_closed'] ?? false),
            'alarm_activated' => (bool) ($data['alarm_activated'] ?? false),
        ];

        if (! $this->securityHandOverSatisfied($order, $handOver)) {
            if ((bool) $order->has_key_safe && ! $handOver['key_returned']) {
                $errors['key_returned'] = 'Der Schlüssel/die Karte muss zurückgegeben werden.';
            }

            if (! $handOver['windows_closed']) {
                $errors['windows_closed'] = 'Fenster und Türen müssen geschlossen sein.';
            }

            if ((bool) $order->has_alarm_system && ! $handOver['alarm_activated']) {
                $errors['alarm_activated'] = 'Die Alarmanlage muss aktiviert werden.';
            }
        }

        // Started with GPS → the close must be GPS-verified too.
        if (! empty($execution->gps_work_start_lat) && empty($data['gps_work_end_lat'])) {
            $errors['gps_work_end_lat'] = 'GPS-Position beim Arbeitsende ist Pflicht.';
        }

        if (! empty($execution->gps_work_start_lng) && empty($data['gps_work_end_lng'])) {
            $errors['gps_work_end_lng'] = 'GPS-Position beim Arbeitsende ist Pflicht.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Raise a geofence alert for every recorded position of the execution that
     * sits further from the site than the configured radius allows.
     *
     * Called after both the start and the closing stamp, so a deviation is
     * reported as soon as it is recorded rather than hours later.
     */
    private function flagGeofenceDeviation(ExtraAuftrag $order, ExtraAuftragExecution $execution): void
    {
        $radius = (float) config('alerts.geofence_radius_meters', 150);

        if ($radius <= 0) {
            return;
        }

        $location = $order->location()->first();

        if (! $location || $location->latitude === null || $location->longitude === null) {
            return;
        }

        $positions = [
            'start' => [$execution->gps_work_start_lat, $execution->gps_work_start_lng],
            'end'   => [$execution->gps_work_end_lat, $execution->gps_work_end_lng],
        ];

        foreach ($positions as $stage => [$lat, $lng]) {
            if ($lat === null || $lng === null) {
                continue;
            }

            $distance = $this->haversineMeters(
                (float) $lat, (float) $lng,
                (float) $location->latitude, (float) $location->longitude
            );

            if ($distance <= $radius) {
                continue;
            }

            app(AdminAlertEvaluator::class)->alertGeofenceDeviation(
                $order, $execution, $stage, $distance, $radius
            );
        }
    }

    /**
     * Great-circle distance in metres.
     */
    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // ── Leader photo & checklist actions ──────────────────────────────────

    public function uploadBeforePhotos(ExtraAuftrag $order, int $userId, array $photos): ExtraAuftragExecution
    {
        $this->assertLeader($order, $userId);
        $execution = $this->getLeaderExecution($order, $userId);

        $existing = $execution->before_photos ?? [];
        $execution->update(['before_photos' => array_merge($existing, $photos)]);

        return $execution->fresh();
    }

    /**
     * Leader uploads after photos. Appends rather than overwrites, matching
     * uploadBeforePhotos, so a second batch can be added without losing the
     * first. Does not change the execution status — the state machine is only
     * advanced by startAfterPhotos / endWork.
     */
    public function uploadAfterPhotos(ExtraAuftrag $order, int $userId, array $photos): ExtraAuftragExecution
    {
        $this->assertLeader($order, $userId);
        $execution = $this->getLeaderExecution($order, $userId);

        $existing = $execution->after_photos ?? [];
        $execution->update(['after_photos' => array_values(array_unique(
            array_merge($existing, $photos)
        ))]);

        return $execution->fresh();
    }

    public function updateChecklist(ExtraAuftrag $order, int $userId, array $checklistItems): ExtraAuftragExecution
    {
        $this->assertLeader($order, $userId);
        $execution = $this->getLeaderExecution($order, $userId);
        $execution->update(['checklist_items' => $checklistItems]);

        return $execution->fresh();
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function uniqueAssignees(array $assignees): array
    {
        return array_values(array_unique(array_map(
            fn($a) => (int) $a['user_id'],
            $assignees,
        )));
    }

    /**
     * Build one booking window per employee and enforce the booking rule.
     *
     * @param  array<int>  $employeeIds
     * @param  array<string>  $ignoreEventIds
     */
    private function assertNoEmployeeConflicts(array $data, array $employeeIds, array $ignoreEventIds = [], bool $force = false): void
    {
        $date = Carbon::parse($data['scheduled_date'] ?? now()->toDateString());

        if (! empty($data['scheduled_time_start'])) {
            $start = Carbon::parse($date->toDateString().' '.$data['scheduled_time_start']);
            $hours = (float) ($data['estimated_hours'] ?? 1);
            $end   = $start->copy()->addHours(max($hours, 0.25));
            $allDay = false;
        } else {
            $start = $date->copy()->startOfDay();
            $end   = $date->copy()->endOfDay();
            $allDay = true;
        }

        $candidates = collect();

        foreach ($employeeIds as $userId) {
            $candidates->push(new \App\ValueObjects\CalendarEvent(
                id: 'extra_auftrag:candidate',
                type: \App\Enums\CalendarEventTypeEnum::ExtraAuftrag,
                layer: 'jobs',
                title: $data['title'] ?? 'Extra-Auftrag',
                startAt: $start,
                endAt: $end,
                allDay: $allDay,
                color: '#3b82f6',
                employeeIds: [$userId],
            ));
        }

        $this->conflicts->assertClean(
            $this->conflicts->findConflicts($candidates, $ignoreEventIds),
            $force
        );
    }

    private function validateLeaderPresent(array $assignees): void
    {
        $hasLeader = collect($assignees)->contains(
            fn($a) => ($a['role_in_order'] ?? '') === AssigneeRoleEnum::Leader->value
        );

        if (! $hasLeader) {
            throw ValidationException::withMessages([
                'assignees' => 'Ein Vorarbeiter (Leader) ist für jeden Auftrag Pflicht.',
            ]);
        }
    }

    private function syncAssignees(ExtraAuftrag $order, array $assignees): void
    {
        // Remove existing, then re-insert
        $order->assignees()->delete();

        foreach ($assignees as $assignee) {
            ExtraAuftragAssignee::create([
                'extra_auftrag_id' => $order->id,
                'user_id'          => $assignee['user_id'],
                'role_in_order'    => $assignee['role_in_order'],
            ]);
        }
    }

    private function assertAssigned(ExtraAuftrag $order, int $userId): void
    {
        if (! $order->isAssigned($userId)) {
            throw new \RuntimeException('Employee is not assigned to this order.');
        }
    }

    private function assertLeader(ExtraAuftrag $order, int $userId): void
    {
        abort_unless($order->isLeader($userId), 403);
    }

    private function ensureExecution(ExtraAuftrag $order, int $userId): ExtraAuftragExecution
    {
        return ExtraAuftragExecution::firstOrCreate(
            ['extra_auftrag_id' => $order->id, 'user_id' => $userId],
            [
                'status'          => ExtraExecutionStatusEnum::Travelling,
                'checklist_items' => $order->checklist_template ?? [],
            ]
        );
    }

    private function getLeaderExecution(ExtraAuftrag $order, int $userId): ExtraAuftragExecution
    {
        return ExtraAuftragExecution::where('extra_auftrag_id', $order->id)
                                    ->where('user_id', $userId)
                                    ->firstOrFail();
    }
}
