<?php

namespace App\Services;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\ExtraExecutionStatusEnum;
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

    // ── Leader photo & checklist actions ──────────────────────────────────

    public function uploadBeforePhotos(ExtraAuftrag $order, int $userId, array $photos): ExtraAuftragExecution
    {
        $this->assertLeader($order, $userId);
        $execution = $this->getLeaderExecution($order, $userId);

        $existing = $execution->before_photos ?? [];
        $execution->update(['before_photos' => array_merge($existing, $photos)]);

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
        if (! $order->isLeader($userId)) {
            throw new \RuntimeException('Only the Vorarbeiter (leader) can perform this action.');
        }
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
