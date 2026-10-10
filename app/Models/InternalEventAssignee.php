<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attendee of a company-internal event.
 *
 * Backed by the unified `personal_event_assignees` table.
 * `internal_event_id` is kept as the public attribute name so existing call
 * sites (controllers, admin views) keep working — it maps
 * onto the `personal_event_id` column.
 */
class InternalEventAssignee extends PersonalEventAssignee
{
    protected $table = 'personal_event_assignees';

    protected $fillable = [
        'internal_event_id',
        'user_id',
    ];

    protected function internalEventId(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->personal_event_id,
            set: fn($value) => ['personal_event_id' => $value],
        );
    }

    public function internalEvent(): BelongsTo
    {
        return $this->belongsTo(InternalEvent::class, 'personal_event_id');
    }
}
