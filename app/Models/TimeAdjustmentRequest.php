<?php

namespace App\Models;

use App\Enums\AdjustmentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeAdjustmentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'job_type',
        'job_id',
        'requested_start',
        'requested_end',
        'original_start',
        'original_end',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
        'offline_uuid',
        'client_submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_start'     => 'datetime',
            'requested_end'       => 'datetime',
            'original_start'      => 'datetime',
            'original_end'        => 'datetime',
            'status'              => AdjustmentStatusEnum::class,
            'reviewed_at'         => 'datetime',
            'client_submitted_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', AdjustmentStatusEnum::Pending);
    }

    public function scopeForEmployee($query, int $userId)
    {
        return $query->where('employee_id', $userId);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === AdjustmentStatusEnum::Pending;
    }
}
