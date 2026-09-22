<?php

namespace App\Models;

use App\Enums\CustomerStatusEnum;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'contact_person',
        'status',
        'notes',
        'portal_access',
        'portal_email',
    ];

    protected function casts(): array
    {
        return [
            'status'         => CustomerStatusEnum::class,
            'portal_access'  => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function locations(): HasMany
    {
        return $this->hasMany(CustomerLocation::class);
    }

    public function activeLocations(): HasMany
    {
        return $this->hasMany(CustomerLocation::class)->where('is_active', true);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', CustomerStatusEnum::Active);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', CustomerStatusEnum::Inactive);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === CustomerStatusEnum::Active;
    }
}
