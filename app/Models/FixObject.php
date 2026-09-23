<?php

namespace App\Models;

use App\Enums\FixFrequencyEnum;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixObject extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    // Financial fields never included in audit old_values (encrypted, no plaintext in logs)
    protected array $auditExclude = [
        'price_per_month', 'price_per_hour', 'internal_cost', 'profit_margin',
    ];

    protected $fillable = [
        'customer_id',
        'location_id',
        'title',
        'frequency',
        'frequency_days',
        'contract_hours',
        'time_start',
        'time_end',
        'valid_from',
        'valid_until',
        'is_active',
        'calendar_color',
        'price_per_month',
        'price_per_hour',
        'internal_cost',
        'profit_margin',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'frequency'       => FixFrequencyEnum::class,
            'frequency_days'  => 'array',
            'contract_hours'  => 'decimal:2',
            'valid_from'      => 'date',
            'valid_until'     => 'date',
            'is_active'       => 'boolean',
            // Encryption at rest for all financial fields
            'price_per_month' => 'encrypted',
            'price_per_hour'  => 'encrypted',
            'internal_cost'   => 'encrypted',
            'profit_margin'   => 'encrypted',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(CustomerLocation::class, 'location_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(FixObjectAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(FixObjectAssignment::class)
                    ->whereNull('assigned_until')
                    ->orWhere('assigned_until', '>=', now()->toDateString());
    }

    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'fix_object_assignments')
                    ->withPivot(['assigned_from', 'assigned_until', 'notes'])
                    ->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(FixObjectSchedule::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEmployee($query, int $userId)
    {
        return $query->whereHas('assignments', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where('assigned_from', '<=', now()->toDateString())
              ->where(function ($q2) {
                  $q2->whereNull('assigned_until')
                     ->orWhere('assigned_until', '>=', now()->toDateString());
              });
        });
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isActiveOn(\Carbon\Carbon $date): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($date->lt($this->valid_from)) {
            return false;
        }
        if ($this->valid_until && $date->gt($this->valid_until)) {
            return false;
        }
        return true;
    }
}
