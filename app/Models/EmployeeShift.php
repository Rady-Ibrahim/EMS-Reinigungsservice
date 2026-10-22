<?php

namespace App\Models;

use App\Enums\ShiftStatusEnum;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeShift extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'user_id',
        'created_by',
        'title',
        'start_at',
        'end_at',
        'work_hours',
        'approved_extra_hours',
        'all_day',
        'color',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at'   => 'datetime',
            'work_hours' => 'decimal:2',
            'approved_extra_hours' => 'decimal:2',
            'all_day'  => 'boolean',
            'status'   => ShiftStatusEnum::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForEmployee($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForDateRange($query, $from, $to)
    {
        return $query->where('start_at', '<=', $to)
                     ->where('end_at', '>=', $from);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function overlaps(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): bool
    {
        return $start->lt($this->end_at) && $this->start_at->lt($end);
    }

    public function scheduledWorkHours(): float
    {
        if ($this->work_hours !== null) {
            return (float) $this->work_hours;
        }

        return round($this->start_at->diffInMinutes($this->end_at) / 60, 2);
    }

    public function reportableHours(): float
    {
        return round($this->scheduledWorkHours() + (float) $this->approved_extra_hours, 2);
    }
}