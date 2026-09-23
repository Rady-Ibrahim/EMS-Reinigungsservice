<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixObjectAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'fix_object_id',
        'user_id',
        'assigned_from',
        'assigned_until',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_from'  => 'date',
            'assigned_until' => 'date',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function fixObject(): BelongsTo
    {
        return $this->belongsTo(FixObject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('assigned_from', '<=', now()->toDateString())
                     ->where(function ($q) {
                         $q->whereNull('assigned_until')
                           ->orWhere('assigned_until', '>=', now()->toDateString());
                     });
    }

    public function scopeForEmployee($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isActiveOn(\Carbon\Carbon $date): bool
    {
        if ($date->lt($this->assigned_from)) {
            return false;
        }
        if ($this->assigned_until && $date->gt($this->assigned_until)) {
            return false;
        }
        return true;
    }
}
