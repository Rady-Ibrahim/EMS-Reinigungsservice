<?php

namespace App\Services;

use App\Models\FixObject;
use App\Models\FixObjectAssignment;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Enums\ExecutionStatusEnum;
use App\Enums\ScheduleStatusEnum;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FixObjectService
{
    public function __construct(
        private readonly ScheduleGeneratorService $scheduleGenerator,
        private readonly ContractHoursCalculator  $calculator,
        private readonly ConflictCheckerService   $conflicts
    ) {
    }

    // ── CRUD ───────────────────────────────────────────────────────────────

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return FixObject::with(['customer:id,name', 'location:id,name,city'])
                        ->withCount('assignments')
                        ->orderBy('title')
                        ->paginate($perPage);
    }

    public function create(array $data): FixObject
    {
        return DB::transaction(function () use ($data) {
            $fixObject = FixObject::create($data);

            // Pre-generate schedules for current + next month
            $this->scheduleGenerator->generateInitialSchedules($fixObject);

            return $fixObject->load(['customer', 'location']);
        });
    }

    public function update(FixObject $fixObject, array $data): FixObject
    {
        return DB::transaction(function () use ($fixObject, $data) {
            $recurrenceChanged = $this->recurrenceChanged($fixObject, $data);

            $fixObject->update($data);

            // If recurrence parameters changed, regenerate pending schedules
            if ($recurrenceChanged) {
                $now  = Carbon::now();
                $next = $now->copy()->addMonth();
                $this->scheduleGenerator->regenerateForMonth($fixObject, $now->year, $now->month);
                $this->scheduleGenerator->regenerateForMonth($fixObject, $next->year, $next->month);
            }

            return $fixObject->fresh(['customer', 'location']);
        });
    }

    public function delete(FixObject $fixObject): void
    {
        $fixObject->delete();
    }

    // ── Assignments ────────────────────────────────────────────────────────

    public function assignEmployee(
        FixObject $fixObject,
        int $userId,
        string $assignedFrom,
        ?string $assignedUntil = null,
        bool $force = false
    ): FixObjectAssignment {
        // Same employee re-assertion (e.g. extending an existing assignment)
        // is not a double-booking — skip the conflict sweep.
        $alreadyAssigned = FixObjectAssignment::where('fix_object_id', $fixObject->id)
            ->where('user_id', $userId)
            ->exists();

        if (! $alreadyAssigned) {
            $candidates = $this->conflicts->fixCandidates($fixObject, $assignedFrom, $assignedUntil, [$userId]);

            if ($candidates->isNotEmpty()) {
                $ignore = $fixObject->schedules()
                    ->pluck('id')
                    ->map(fn($id) => 'fix_schedule:'.$id)
                    ->all();

                $this->conflicts->assertClean(
                    $this->conflicts->findConflicts($candidates, $ignore),
                    $force
                );
            }
        }

        return FixObjectAssignment::create([
            'fix_object_id' => $fixObject->id,
            'user_id'       => $userId,
            'assigned_from' => $assignedFrom,
            'assigned_until'=> $assignedUntil,
        ]);
    }

    public function removeAssignment(FixObjectAssignment $assignment): void
    {
        $assignment->update(['assigned_until' => now()->toDateString()]);
    }

    // ── Execution workflow ─────────────────────────────────────────────────

    /**
     * Employee starts a scheduled job.
     * Creates the execution record and freezes contract_hours_applied.
     */
    public function startExecution(FixObjectSchedule $schedule, int $userId, array $data): FixObjectExecution
    {
        // Verify employee is assigned to this fix object —
        // either via the contract assignment or a per-day schedule override
        $fixObject = $schedule->fixObject;
        $isAssigned = $schedule->scheduleAssignments()
            ->where('user_id', $userId)
            ->exists()
            || $fixObject->assignments()
                ->where('user_id', $userId)
                ->active()
                ->exists();

        if (! $isAssigned) {
            throw new \RuntimeException('Employee is not assigned to this Fixobjekt.');
        }

        return DB::transaction(function () use ($schedule, $userId, $data, $fixObject) {
            $execution = FixObjectExecution::create([
                'schedule_id'            => $schedule->id,
                'user_id'                => $userId,
                'actual_start'           => $data['actual_start'] ?? now(),
                'gps_start_lat'          => $data['gps_start_lat'] ?? null,
                'gps_start_lng'          => $data['gps_start_lng'] ?? null,
                'status'                 => ExecutionStatusEnum::Started,
                // FREEZE contract hours at the moment of execution start
                'contract_hours_applied' => $fixObject->contract_hours,
                'offline_uuid'           => $data['offline_uuid'] ?? null,
                'client_submitted_at'    => $data['client_submitted_at'] ?? null,
            ]);

            $schedule->update(['status' => ScheduleStatusEnum::InProgress]);

            return $execution;
        });
    }

    /**
     * Advance the execution workflow one step.
     */
    public function advanceStatus(FixObjectExecution $execution, ExecutionStatusEnum $nextStatus, array $data = []): FixObjectExecution
    {
        $execution->transitionTo($nextStatus);

        // Attach photos when relevant
        if ($nextStatus === ExecutionStatusEnum::PhotosBefore && isset($data['before_photos'])) {
            $execution->update(['before_photos' => $data['before_photos']]);
        }

        if ($nextStatus === ExecutionStatusEnum::PhotosAfter && isset($data['after_photos'])) {
            $execution->update(['after_photos' => $data['after_photos']]);
        }

        return $execution->fresh();
    }

    /**
     * Complete the execution — record end time, GPS, and mark schedule done.
     */
    public function completeExecution(FixObjectExecution $execution, array $data): FixObjectExecution
    {
        if (! $execution->status->canTransitionTo(ExecutionStatusEnum::Completed)) {
            throw new \RuntimeException('Execution is not in a completable state.');
        }

        return DB::transaction(function () use ($execution, $data) {
            $execution->update([
                'status'         => ExecutionStatusEnum::Completed,
                'actual_end'     => $data['actual_end'] ?? now(),
                'gps_end_lat'    => $data['gps_end_lat'] ?? null,
                'gps_end_lng'    => $data['gps_end_lng'] ?? null,
                'employee_notes' => $data['employee_notes'] ?? null,
            ]);

            // Mark the parent schedule as completed
            $execution->schedule->update(['status' => ScheduleStatusEnum::Completed]);

            return $execution->fresh();
        });
    }

    // ── Contract hours ─────────────────────────────────────────────────────

    public function monthlyContractHours(FixObject $fixObject, int $year, int $month): float
    {
        return $this->calculator->monthlyHours($fixObject, $year, $month);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function recurrenceChanged(FixObject $fixObject, array $data): bool
    {
        $recurrenceFields = ['frequency', 'frequency_days', 'time_start', 'time_end', 'valid_from', 'valid_until'];

        foreach ($recurrenceFields as $field) {
            if (isset($data[$field]) && $data[$field] != $fixObject->$field) {
                return true;
            }
        }

        return false;
    }
}
