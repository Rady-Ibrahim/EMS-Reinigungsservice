<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Placeholder model for 2FA.
 * Full TOTP logic (QR generation, code verification, recovery) arrives in Phase 7.
 */
class TwoFactorAuth extends Model
{
    protected $table = 'two_factor_auth';

    protected $fillable = [
        'user_id',
        'secret',
        'recovery_codes',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'secret'         => 'encrypted',
            'recovery_codes' => 'encrypted:array',
            'confirmed_at'   => 'datetime',
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
        return $this->confirmed_at !== null;
    }
}
