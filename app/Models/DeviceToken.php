<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Push device token for OneSignal delivery.
 *
 * `token` holds the OneSignal Player ID (the per-installation subscription id
 * from the OneSignal SDK) for `provider = onesignal` rows.
 */
class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform',
        'provider',
        'token',
        'device_name',
        'last_used_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'is_active'    => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Active OneSignal subscriptions for the given user, newest activity first.
     *
     * @return Collection<int, DeviceToken>
     */
    public function scopeActiveOneSignal($query)
    {
        return $query->where('provider', 'onesignal')->where('is_active', true);
    }
}
