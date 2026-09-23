<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Day-level (per-schedule) employee override — the "Dynamic Assignment"
 * building block. Overrides the contract-level assignment for one day.
 *
 * The schedule's effective employee = latest override, otherwise the
 * contract assignment active on the schedule date.
 */
class FixObjectScheduleAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'user_id',
        'created_by',
        'reason',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(FixObjectSchedule::class, 'schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForSchedule($query, int $scheduleId)
    {
        return $query->where('schedule_id', $scheduleId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}