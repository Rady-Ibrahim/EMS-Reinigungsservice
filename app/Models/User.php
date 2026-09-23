<?php

namespace App\Models;

use App\Enums\RoleEnum;
use App\Traits\HasAuditLog;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, HasAuditLog;

    // Never log password changes into the audit trail
    protected array $auditExclude = ['password', 'remember_token', 'last_login_at'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'locale',
        'is_active',
        'last_login_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'role'              => RoleEnum::class,
            'is_active'         => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function twoFactorAuth(): HasOne
    {
        return $this->hasOne(TwoFactorAuth::class);
    }

    public function timeAdjustmentRequests(): HasMany
    {
        return $this->hasMany(TimeAdjustmentRequest::class, 'employee_id');
    }

    public function employeeShifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function personalAppointments(): HasMany
    {
        return $this->hasMany(PersonalAppointment::class);
    }

    public function internalEventAssignees(): HasMany
    {
        return $this->hasMany(InternalEventAssignee::class);
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(FixObjectScheduleAssignment::class);
    }

    // ── Role helpers ──────────────────────────────────────────────────────

    public function isAdministrator(): bool
    {
        return $this->role === RoleEnum::Administrator;
    }

    public function isVorarbeiter(): bool
    {
        return $this->role === RoleEnum::Vorarbeiter;
    }

    public function isMitarbeiter(): bool
    {
        return $this->role === RoleEnum::Mitarbeiter;
    }

    /** True for Vorarbeiter OR Mitarbeiter — they use the mobile API. */
    public function isApiUser(): bool
    {
        return $this->role->isApiRole();
    }
}
