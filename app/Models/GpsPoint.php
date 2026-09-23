<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsPoint extends Model
{
    // GPS history is immutable — never update a recorded point
    public $timestamps  = false;
    public $updatedAt   = false;

    protected $fillable = [
        'travel_track_id',
        'user_id',
        'latitude',
        'longitude',
        'recorded_at',
        'accuracy_meters',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude'        => 'decimal:8',
            'longitude'       => 'decimal:8',
            'recorded_at'     => 'datetime',
            'created_at'      => 'datetime',
            'accuracy_meters' => 'float',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function travelTrack(): BelongsTo
    {
        return $this->belongsTo(TravelTrack::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
