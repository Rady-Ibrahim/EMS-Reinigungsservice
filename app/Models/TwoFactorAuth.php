<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TOTP two-factor authentication for Administrator accounts.
 * Secret and recovery codes are stored encrypted; full TOTP logic lives in
 * App\Services\TwoFactorAuthService.
 */
class TwoFactorAuth extends Model
{
    protected $table = 'two_factor_auth';

    protected $fillable = [
        'user_id',
        'secret',
        'recovery_codes',
        'confirmed_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'secret'         => 'encrypted',
            'recovery_codes' => 'encrypted:array',
            'confirmed_at'   => 'datetime',
            'last_used_at'   => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isEnabled(): bool
    {
        return $this->confirmed_at !== null && filled($this->secret);
    }
}
