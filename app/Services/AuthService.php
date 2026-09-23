<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Attempt to find and authenticate a user by credentials.
     *
     * @param  array{email: string, password: string}  $credentials
     * @return User|null
     */
    public function attemptLogin(array $credentials): ?User
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * Record the last login timestamp.
     */
    public function recordLogin(User $user): void
    {
        $user->timestamps = false;
        $user->last_login_at = now();
        $user->save();
        $user->timestamps = true;
    }

    /**
     * Create a Sanctum API token for mobile users.
     *
     * @return array{token: string, token_type: string}
     */
    public function createApiToken(User $user): array
    {
        // Revoke old tokens to enforce single-device login (adjust if multi-device needed)
        $user->tokens()->delete();

        $token = $user->createToken(
            name: 'mobile-app',
            abilities: $this->tokenAbilities($user),
        );

        return [
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Define token abilities based on role.
     */
    private function tokenAbilities(User $user): array
    {
        return match($user->role) {
            \App\Enums\RoleEnum::Vorarbeiter => [
                'jobs:view', 'jobs:close', 'jobs:upload',
                'checklist:update', 'time:track', 'profile:view',
                'calendar:view', 'appointments:manage', 'reassign:manage',
                'notifications:manage', 'reports:view',
            ],
            \App\Enums\RoleEnum::Mitarbeiter => [
                'jobs:view', 'time:track', 'profile:view',
                'calendar:view', 'appointments:manage',
                'notifications:manage', 'reports:view',
            ],
            default => [],
        };
    }
}
