<?php

namespace App\Models;

use App\Enums\ExecutionStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixObjectExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'user_id',
        'actual_start',
        'actual_end',
        'gps_start_lat',
        'gps_start_lng',
        'gps_end_lat',
        'gps_end_lng',
        'status',
        'before_photos',
        'after_photos',
        'employee_notes',
        'contract_hours_applied',
        'offline_uuid',
        'client_submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'actual_start'           => 'datetime',
            'actual_end'             => 'datetime',
            'gps_start_lat'          => 'decimal:8',
            'gps_start_lng'          => 'decimal:8',
            'gps_end_lat'            => 'decimal:8',
            'gps_end_lng'            => 'decimal:8',
            'status'                 => ExecutionStatusEnum::class,
            'before_photos'          => 'array',
            'after_photos'           => 'array',
            'contract_hours_applied' => 'decimal:2',
            'client_submitted_at'    => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(FixObjectSchedule::class, 'schedule_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForEmployee($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', ExecutionStatusEnum::Completed);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->status === ExecutionStatusEnum::Completed;
    }

    /**
     * Actual duration in minutes (null if not yet finished).
     */
    public function actualDurationMinutes(): ?int
    {
        if (! $this->actual_end) {
            return null;
        }
        return (int) $this->actual_start->diffInMinutes($this->actual_end);
    }

    /**
     * Transition to next workflow status.
     * Throws if transition is not allowed.
     */
    public function transitionTo(ExecutionStatusEnum $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new \LogicException(
                "Cannot transition from [{$this->status->value}] to [{$next->value}]."
            );
        }
        $this->update(['status' => $next]);
    }
}
