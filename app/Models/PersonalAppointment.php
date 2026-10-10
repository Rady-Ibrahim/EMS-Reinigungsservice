<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * A single user's private calendar entry (Meine Termine).
 *
 * Now backed by the unified `personal_events` table — see {@see PersonalEvent}.
 * This class is kept as the public name for a private appointment so the API
 * and the views keep working unchanged.
 *
 * These are NOT work orders: they never create paid work hours and never
 * count toward time tracking.
 */
class PersonalAppointment extends PersonalEvent
{
    protected $table = 'personal_events';

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

    protected static function booted(): void
    {
        // A private appointment can only ever be a `personal_appointment` row.
        static::addGlobalScope('personal_appointment_source', function (Builder $query) {
            $query->where('source', self::SOURCE_APPOINTMENT);
        });

        // Private appointments are private by definition.
        static::creating(function (self $event) {
            $event->source = self::SOURCE_APPOINTMENT;
            $event->is_private = true;
            $event->event_type = $event->event_type ?? \App\Enums\InternalEventTypeEnum::Other;
        });
    }

    /**
     * `color` is the historical column name; the unified table stores
     * `color_code`. This alias keeps every existing call site working.
     */
    protected function color(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->color_code,
            set: fn($value) => ['color_code' => $value],
        );
    }

    /**
     * Private appointments belong to exactly one person.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * A private appointment never has attendees — only its owner.
     *
     * @return array<int>
     */
    public function participatingUserIds(): array
    {
        return [$this->user_id];
    }
}
