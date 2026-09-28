<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An attendee of a company-wide personal event.
 */
class PersonalEventAssignee extends Model
{
    use HasFactory;

    protected $table = 'personal_event_assignees';

    protected $fillable = [
        'personal_event_id',
        'user_id',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PersonalEvent::class, 'personal_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
