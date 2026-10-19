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
        'phone',
        'password',
        'role',
        'company_id',
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

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function monthlyReports(): HasMany
    {
        return $this->hasMany(MonthlyReport::class, 'user_id');
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

    // ── Notification routing ───────────────────────────────────────────────

    /**
     * OneSignal Player IDs for Laravel's Notification system.
     *
     * A crew member can register up to config('push.max_devices_per_user')
     * phones, so this returns every active subscription rather than a single id.
     * App\Notifications\Channels\OneSignalDeviceChannel fans the notification
     * out across all of them.
     *
     * @return array<int, string>
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function routeNotificationForOneSignal(): array
    {
        return $this->deviceTokens()
            ->activeOneSignal()
            ->orderByDesc('last_used_at')
            ->limit((int) config('push.max_devices_per_user', 5))
            ->pluck('token')
            ->all();
    }
}
