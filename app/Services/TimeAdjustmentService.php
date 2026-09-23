<?php

namespace App\Services;

use App\Enums\AdjustmentStatusEnum;
use App\Enums\AuditEventEnum;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObjectExecution;
use App\Models\TimeAdjustmentRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TimeAdjustmentService
{
    public function __construct(
        private readonly TravelTimeCalculatorService $travelCalculator,
        private readonly AuditLogger                  $audit
    ) {
    }

    // ── Employee actions ───────────────────────────────────────────────────

    /**
     * Employee submits a time adjustment request.
     * Automatically captures original times from the existing execution.
     */
    public function submit(array $data): TimeAdjustmentRequest
    {
        [$originalStart, $originalEnd] = $this->fetchOriginalTimes(
            $data['job_type'],
            $data['job_id'],
            $data['employee_id']
        );

        return TimeAdjustmentRequest::create([
            'employee_id'         => $data['employee_id'],
            'job_type'            => $data['job_type'],
            'job_id'              => $data['job_id'],
            'requested_start'     => $data['requested_start'],
            'requested_end'       => $data['requested_end'],
            'original_start'      => $originalStart,   // frozen — never changes
            'original_end'        => $originalEnd,      // frozen — never changes
            'reason'              => $data['reason'],
            'status'              => AdjustmentStatusEnum::Pending,
            'offline_uuid'        => $data['offline_uuid'] ?? null,
            'client_submitted_at' => $data['client_submitted_at'] ?? now(),
        ]);
    }

    /**
     * Admin approves a request and applies the adjusted times to the execution.
     *
     * For Fixobjekte:
     *   - Updates actual_start + actual_end only
     *   - contract_hours_applied is NEVER touched
     *
     * For Extra-Aufträge:
     *   - Updates work_start + work_end
     *   - Recalculates work_minutes + paid_minutes
     */
    public function approve(TimeAdjustmentRequest $request, User $admin, ?string $adminNote = null): TimeAdjustmentRequest
    {
        return DB::transaction(function () use ($request, $admin, $adminNote) {
            // Stamp the reviewer first so the audit trail records the correct actor.
            $request->update(['reviewed_by' => $admin->id]);

            // Apply the adjustment to the relevant execution
            $this->applyAdjustment($request);

            // Update request status with audit trail
            $request->update([
                'status'      => AdjustmentStatusEnum::Approved,
                'reviewed_at' => now(),
                'admin_note'  => $adminNote,
            ]);

            return $request->fresh(['employee', 'reviewer']);
        });
    }

    /**
     * Admin rejects a request — no changes to execution times.
     */
    public function reject(TimeAdjustmentRequest $request, User $admin, string $adminNote): TimeAdjustmentRequest
    {
        $request->update([
            'status'      => AdjustmentStatusEnum::Rejected,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'admin_note'  => $adminNote,
        ]);

        return $request->fresh(['employee', 'reviewer']);
    }

    // ── Admin queries ──────────────────────────────────────────────────────

    public function pendingRequests(int $perPage = 20): LengthAwarePaginator
    {
        return TimeAdjustmentRequest::with(['employee:id,name,role'])
            ->pending()
            ->orderBy('client_submitted_at')
            ->paginate($perPage);
    }

    public function allRequests(int $perPage = 20): LengthAwarePaginator
    {
        return TimeAdjustmentRequest::with(['employee:id,name', 'reviewer:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function employeeRequests(int $userId): LengthAwarePaginator
    {
        return TimeAdjustmentRequest::forEmployee($userId)
            ->with(['reviewer:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Apply adjusted times to the correct execution record.
     */
    private function applyAdjustment(TimeAdjustmentRequest $request): void
    {
        if ($request->job_type === 'fix_object') {
            $this->applyToFixExecution($request);
        } else {
            $this->applyToExtraExecution($request);
        }
    }

    private function applyToFixExecution(TimeAdjustmentRequest $request): void
    {
        $execution = FixObjectExecution::where('schedule_id', $request->job_id)
                                       ->where('user_id', $request->employee_id)
                                       ->firstOrFail();

        // ONLY update actual times — contract_hours_applied is sacred
        $execution->update([
            'actual_start' => $request->requested_start,
            'actual_end'   => $request->requested_end,
        ]);

        $this->audit->record(
            $execution,
            AuditEventEnum::HoursAdjusted,
            oldValues: [
                'actual_start' => $request->original_start?->toIso8601String(),
                'actual_end'   => $request->original_end?->toIso8601String(),
            ],
            newValues: [
                'actual_start' => $request->requested_start->toIso8601String(),
                'actual_end'   => $request->requested_end->toIso8601String(),
            ],
            reason: 'Zeitkorrektur genehmigt (Fixobjekt)',
            actorId: $request->reviewed_by,
        );
    }

    private function applyToExtraExecution(TimeAdjustmentRequest $request): void
    {
        $execution = ExtraAuftragExecution::where('extra_auftrag_id', $request->job_id)
                                          ->where('user_id', $request->employee_id)
                                          ->firstOrFail();

        $newStart    = $request->requested_start;
        $newEnd      = $request->requested_end;
        $workMinutes = max(0, (int) $newStart->diffInMinutes($newEnd));

        // Recalculate paid_minutes with new work time
        $travelTrack = \App\Models\TravelTrack::where('extra_auftrag_id', $request->job_id)
                                              ->where('user_id', $request->employee_id)
                                              ->first();

        $travelPaid  = ($travelTrack && $travelTrack->is_paid)
            ? ($travelTrack->travel_minutes ?? 0)
            : 0;

        $paidMinutes = $workMinutes + $travelPaid;

        $oldValues = [
            'work_start'   => $execution->work_start?->toIso8601String(),
            'work_end'     => $execution->work_end?->toIso8601String(),
            'work_minutes' => $execution->work_minutes,
            'paid_minutes' => $execution->paid_minutes,
        ];

        $execution->update([
            'work_start'   => $newStart,
            'work_end'     => $newEnd,
            'work_minutes' => $workMinutes,
            'paid_minutes' => $paidMinutes,
        ]);

        $this->audit->record(
            $execution,
            AuditEventEnum::HoursAdjusted,
            oldValues: $oldValues,
            newValues: [
                'work_start'   => $newStart->toIso8601String(),
                'work_end'     => $newEnd->toIso8601String(),
                'work_minutes' => $workMinutes,
                'paid_minutes' => $paidMinutes,
            ],
            reason: 'Zeitkorrektur genehmigt (Extra-Auftrag)',
            actorId: $request->reviewed_by,
        );
    }

    /**
     * Extract current actual times from the job execution.
     * Returns [start, end] — both may be null if not yet recorded.
     */
    private function fetchOriginalTimes(string $jobType, int $jobId, int $employeeId): array
    {
        if ($jobType === 'fix_object') {
            $execution = FixObjectExecution::where('schedule_id', $jobId)
                                           ->where('user_id', $employeeId)
                                           ->first();
            return [$execution?->actual_start, $execution?->actual_end];
        }

        $execution = ExtraAuftragExecution::where('extra_auftrag_id', $jobId)
                                          ->where('user_id', $employeeId)
                                          ->first();
        return [$execution?->work_start, $execution?->work_end];
    }
}
