<?php

namespace App\Models;

use App\Enums\InternalEventTypeEnum;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A personal or internal appointment in the unified calendar ("Meine Termine").
 *
 * A personal event is:
 *   - owned by one user (`user_id`)
 *   - optionally attended by others (`personal_event_assignees`)
 *   - typed as inspection / meeting / call / reminder / other
 *   - flagged private, in which case only the owner ever sees it
 *
 * IMPORTANT: personal events are NOT work orders. They never produce paid work
 * hours, never appear in time tracking and never count towards a monthly
 * report. Only Fixobjekt schedules and Extra-Aufträge do.
 *
 * The `source` column records which of the two historical concepts the row
 * represents, so {@see PersonalAppointment} and {@see InternalEvent} remain
 * thin, behaviour-preserving views onto this one table.
 */
class PersonalEvent extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    public const SOURCE_APPOINTMENT = 'personal_appointment';
    public const SOURCE_INTERNAL    = 'internal_event';

    /** Default colour per event type when none is chosen. */
    public const DEFAULT_COLORS = [
        'inspection' => '#f59e0b',
        'meeting'    => '#f59e0b',
        'call'       => '#0ea5e9',
        'reminder'   => '#8b5cf6',
        'other'      => '#8b5cf6',
    ];

    protected $table = 'personal_events';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'event_type',
        'start_at',
        'end_at',
        'color_code',
        'is_private',
        'all_day',
        'location',
        'reminders',
        'source',
        'source_id',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => InternalEventTypeEnum::class,
            'start_at'   => 'datetime',
            'end_at'     => 'datetime',
            'all_day'    => 'boolean',
            'is_private' => 'boolean',
            'reminders'  => 'array',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(PersonalEventAssignee::class);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'personal_event_assignees')
                    ->withTimestamps();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /** Events the given user owns OR attends. */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereHas('assignees', fn($a) => $a->where('user_id', $userId));
        });
    }

    public function scopeForDateRange(Builder $query, $from, $to): Builder
    {
        return $query->where('start_at', '<=', $to)
                     ->where('end_at', '>=', $from);
    }

    public function scopeOfSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * Hide other people's private entries.
     *
     * @param  int|null  $viewerId  null = no viewer (admin sees everything)
     */
    public function scopeVisibleTo(Builder $query, ?int $viewerId): Builder
    {
        if ($viewerId === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($viewerId) {
            $q->where('is_private', false)->orWhere('user_id', $viewerId);
        });
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    /** User ids whose time this event occupies (owner + attendees). */
    public function participatingUserIds(): array
    {
        return array_values(array_unique(array_merge(
            [$this->user_id],
            $this->assignees->pluck('user_id')->all()
        )));
    }

    public function displayColor(): string
    {
        return $this->color_code
            ?: (self::DEFAULT_COLORS[$this->event_type->value] ?? self::DEFAULT_COLORS['other']);
    }

    public function overlaps(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): bool
    {
        return $start->lt($this->end_at) && $this->start_at->lt($end);
    }

    public function isCompanyEvent(): bool
    {
        return $this->source === self::SOURCE_INTERNAL;
    }
}
