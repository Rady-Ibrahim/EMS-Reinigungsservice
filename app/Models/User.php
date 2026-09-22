<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

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

    // -------------------------------------------------------------------------
    // Role helpers
    // -------------------------------------------------------------------------

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
