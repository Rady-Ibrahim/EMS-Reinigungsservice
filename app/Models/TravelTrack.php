<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelTrack extends Model
{
    use HasFactory;

    protected $fillable = [
        'extra_auftrag_id',
        'user_id',
        'departure_at',
        'arrival_at',
        'gps_departure_lat',
        'gps_departure_lng',
        'gps_arrival_lat',
        'gps_arrival_lng',
        'travel_minutes',
        'is_paid',
        'offline_uuid',
    ];

    protected function casts(): array
    {
        return [
            'departure_at'       => 'datetime',
            'arrival_at'         => 'datetime',
            'gps_departure_lat'  => 'decimal:8',
            'gps_departure_lng'  => 'decimal:8',
            'gps_arrival_lat'    => 'decimal:8',
            'gps_arrival_lng'    => 'decimal:8',
            'is_paid'            => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function extraAuftrag(): BelongsTo
    {
        return $this->belongsTo(ExtraAuftrag::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function gpsPoints(): HasMany
    {
        return $this->hasMany(GpsPoint::class)->orderBy('recorded_at');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function hasArrived(): bool
    {
        return $this->arrival_at !== null;
    }

    public function paidMinutes(): int
    {
        if (! $this->is_paid || ! $this->travel_minutes) {
            return 0;
        }
        return $this->travel_minutes;
    }
}
