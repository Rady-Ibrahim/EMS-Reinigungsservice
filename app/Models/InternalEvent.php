<?php

namespace App\Models;

use App\Enums\InternalEventTypeEnum;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Company-internal events (Begehung, Besprechung, Anruf, Erinnerung).
 * Like personal appointments they never produce paid work hours.
 */
class InternalEvent extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    protected $fillable = [
        'created_by',
        'title',
        'event_type',
        'start_at',
        'end_at',
        'all_day',
        'location',
        'description',
        'color',
        'reminders',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => InternalEventTypeEnum::class,
            'start_at'   => 'datetime',
            'end_at'     => 'datetime',
            'all_day'    => 'boolean',
            'reminders'  => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(InternalEventAssignee::class);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'internal_event_assignees')
                    ->withTimestamps();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('created_by', $userId)
              ->orWhereHas('assignees', fn($a) => $a->where('user_id', $userId));
        });
    }

    public function scopeForDateRange($query, $from, $to)
    {
        return $query->where('start_at', '<=', $to)
                     ->where('end_at', '>=', $from);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** User ids occupying this event's time (creator + attendees). */
    public function participatingUserIds(): array
    {
        return array_values(array_unique(array_merge(
            [$this->created_by],
            $this->assignees->pluck('user_id')->all()
        )));
    }

    public function overlaps(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): bool
    {
        return $start->lt($this->end_at) && $this->start_at->lt($end);
    }
}