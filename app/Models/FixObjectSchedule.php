<?php

namespace App\Models;

use App\Enums\ScheduleStatusEnum;
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
}
