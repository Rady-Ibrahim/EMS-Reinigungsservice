<?php

namespace App\Models;

use App\Enums\ContractTypeEnum;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeProfile extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    protected $fillable = [
        'user_id',
        'calendar_color',
        'employee_number',
        'phone',
        'address',
        'iban',
        'hourly_rate',
        'contract_type',
        'joined_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'contract_type' => ContractTypeEnum::class,
            'joined_at'     => 'date',
            // Encryption at rest — these values are opaque in the database
            'iban'          => 'encrypted',
            'hourly_rate'   => 'encrypted',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Hourly rate as float (decrypted by cast, cast back to float for arithmetic).
     */
    public function getHourlyRateAsFloat(): ?float
    {
        return $this->hourly_rate !== null ? (float) $this->hourly_rate : null;
    }
}
