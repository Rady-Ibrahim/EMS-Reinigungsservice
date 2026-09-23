<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalEventAssignee extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_event_id',
        'user_id',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function internalEvent(): BelongsTo
    {
        return $this->belongsTo(InternalEvent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}