<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personal calendar of a single user (Meine Termine).
 *
 * These are NOT work orders — they never create paid work hours or
 * count toward time tracking. They can be toggled as an independent layer.
 */
class PersonalAppointment extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'user_id',
        'title',
        'start_at',
        'end_at',
        'all_day',
        'description',
        'location',
        'color',
        'reminders',
    ];

    protected function casts(): array
    {
        return [
            'start_at'  => 'datetime',
            'end_at'    => 'datetime',
            'all_day'   => 'boolean',
            'reminders' => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForDateRange($query, $from, $to)
    {
        return $query->where('start_at', '<=', $to)
                     ->where('end_at', '>=', $from);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    public function overlaps(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): bool
    {
        return $start->lt($this->end_at) && $this->start_at->lt($end);
    }
}