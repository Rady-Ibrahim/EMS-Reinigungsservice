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
}