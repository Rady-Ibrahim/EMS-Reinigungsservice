<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company-internal event (Begehung, Besprechung, Anruf, Erinnerung).
 *
 * Now backed by the unified `personal_events` table — see {@see PersonalEvent}.
 * It is created by an administrator and can have several attendees; unlike a
 * private appointment it is therefore never private.
 *
 * Like personal appointments, internal events never produce paid work hours
 * and are not work orders for the employees involved.
 */
class InternalEvent extends PersonalEvent
{
    protected $table = 'personal_events';

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

    protected static function booted(): void
    {
        static::addGlobalScope('internal_event_source', function (Builder $query) {
            $query->where('source', self::SOURCE_INTERNAL);
        });

        static::creating(function (self $event) {
            $event->source = self::SOURCE_INTERNAL;
            $event->is_private = false;
        });
    }

    /**
     * `created_by` is the historical column name; the unified table stores the
     * owner in `user_id`.
     */
    protected function createdBy(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->user_id,
            set: fn($value) => ['user_id' => $value],
        );
    }

    /**
     * `color` is the historical column name; the unified table stores
     * `color_code`.
     */
    protected function color(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->color_code,
            set: fn($value) => ['color_code' => $value],
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(PersonalEventAssignee::class, 'personal_event_id');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'personal_event_assignees')
                    ->withTimestamps();
    }

    /**
     * Attendees only — the creator is not implicitly an attendee.
     */
    public function attendeeIds(): array
    {
        return $this->assignees->pluck('user_id')->all();
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereHas('assignees', fn($a) => $a->where('user_id', $userId));
        });
    }
}
