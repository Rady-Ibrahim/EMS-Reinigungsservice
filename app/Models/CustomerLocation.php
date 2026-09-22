<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerLocation extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    protected $fillable = [
        'customer_id',
        'name',
        'street',
        'house_number',
        'postal_code',
        'city',
        'country',
        'latitude',
        'longitude',
        'contact_person',
        'contact_phone',
        'access_instructions',
        'security_code',
        'service_checklist',
        'working_days',
        'working_hours_start',
        'working_hours_end',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude'           => 'decimal:8',
            'longitude'          => 'decimal:8',
            'service_checklist'  => 'array',
            'working_days'       => 'array',
            'is_active'          => 'boolean',
            // Encryption at rest for sensitive operational data
            'security_code'      => 'encrypted',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(LocationFile::class, 'location_id');
    }

    public function externFiles(): HasMany
    {
        return $this->hasMany(LocationFile::class, 'location_id')
                    ->where('visibility', 'extern');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function fullAddress(): string
    {
        return "{$this->street} {$this->house_number}, {$this->postal_code} {$this->city}";
    }
}
