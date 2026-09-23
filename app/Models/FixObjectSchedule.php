<?php

namespace App\Models;

use App\Enums\ScheduleStatusEnum;
use App\Models\FixObjectAssignment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixObjectSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'fix_object_id',
        'scheduled_date',
        'scheduled_start',
        'scheduled_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'status'         => ScheduleStatusEnum::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function fixObject(): BelongsTo
    {
        return $this->belongsTo(FixObject::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(FixObjectExecution::class, 'schedule_id');
    }

    public function executionForEmployee(int $userId): ?FixObjectExecution
    {
        return $this->executions()->where('user_id', $userId)->first();
    }

    /**
     * Day-level reassignments for this schedule (Dynamic Assignment).
     */
    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(FixObjectScheduleAssignment::class, 'schedule_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', ScheduleStatusEnum::Pending);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('scheduled_date', $year)
                     ->whereMonth('scheduled_date', $month);
    }

    public function scopeForDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('scheduled_date', [$from, $to]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === ScheduleStatusEnum::Pending;
    }

    public function isCompleted(): bool
    {
        return $this->status === ScheduleStatusEnum::Completed;
    }

    // ── Effective assignee (Dynamic Assignment) ────────────────────────────

    /**
     * The latest day-level override, if any. NULL means the contract-level
     * assignment applies.
     */
    public function override(): ?FixObjectScheduleAssignment
    {
        return $this->scheduleAssignments()->latest('id')->first();
    }

    /**
     * Employees responsible for THIS schedule day.
     * Day-level override wins; otherwise the contract assignment(s) active
     * on the schedule date apply.
     */
    public function effectiveEmployeeIds(): array
    {
        $overrides = $this->relationLoaded('scheduleAssignments')
            ? $this->scheduleAssignments
            : null;

        if ($overrides && $overrides->isNotEmpty()) {
            return [$overrides->sortByDesc('id')->first()->user_id];
        }

        if ($overrides === null) {
            if ($override = $this->scheduleAssignments()->latest('id')->first()) {
                return [$override->user_id];
            }
        }

        $date  = $this->scheduled_date;
        $fixId = $this->fix_object_id;

        $rows = ($this->relationLoaded('fixObject') && $this->fixObject->relationLoaded('assignments'))
            ? $this->fixObject->assignments->whereStrict('fix_object_id', $fixId)
            : FixObjectAssignment::where('fix_object_id', $fixId)->get();

        return $rows
            ->filter(fn(FixObjectAssignment $a) => $a->isActiveOn(\Carbon\Carbon::parse($date)))
            ->pluck('user_id')
            ->values()
            ->all();
    }
}
