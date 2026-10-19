<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A subcontractor company whose own employees (users.company_id) carry out
 * Fixobjekte and Extra-Aufträge. Used to group the workforce and to record the
 * amount a company earns per operation (fix_objects/extra_auftraege.company_payout).
 */
class Company extends Model
{
    use HasAuditLog, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'contact_person',
        'notes',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(User::class)
            ->whereIn('role', ['vorarbeiter', 'mitarbeiter']);
    }

    public function fixObjects(): HasMany
    {
        return $this->hasMany(FixObject::class);
    }

    public function extraOrders(): HasMany
    {
        return $this->hasMany(ExtraAuftrag::class);
    }
}
