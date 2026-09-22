<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN_URL = '/api/v1/auth/login';
    private const LOGOUT_URL = '/api/v1/auth/logout';
    private const ME_URL = '/api/v1/auth/me';

    // ──────────────────────────────────────────────────────────────────────
    // Vorarbeiter Login
    // ──────────────────────────────────────────────────────────────────────

    public function test_vorarbeiter_can_login_via_api(): void
    {
        $user = User::factory()->vorarbeiter()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => ['user' => ['id', 'name', 'email', 'role', 'locale'], 'token', 'token_type'],
            ])
            ->assertJsonPath('data.user.role', RoleEnum::Vorarbeiter->value)
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Mitarbeiter Login
    // ──────────────────────────────────────────────────────────────────────

    public function test_mitarbeiter_can_login_via_api(): void
    {
        $user = User::factory()->mitarbeiter()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.role', RoleEnum::Mitarbeiter->value);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Administrator blocked from API
    // ──────────────────────────────────────────────────────────────────────

    public function test_administrator_cannot_login_via_api(): void
    {
        $admin = User::factory()->administrator()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $admin->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Inactive user
    // ──────────────────────────────────────────────────────────────────────

    public function test_inactive_user_cannot_login_via_api(): void
    {
        $user = User::factory()->mitarbeiter()->inactive()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Wrong credentials
    // ──────────────────────────────────────────────────────────────────────

    public function test_api_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->mitarbeiter()->create([
            'password' => bcrypt('correct-pass'),
        ]);

        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'wrong-pass',  // valid length, wrong value
        ]);

        $response->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Token-protected routes
    // ──────────────────────────────────────────────────────────────────────

    public function test_me_endpoint_returns_user_data(): void
    {
        $user = User::factory()->vorarbeiter()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson(self::ME_URL);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'role', 'role_label', 'locale', 'last_login_at'],
            ])
            ->assertJsonPath('data.role', RoleEnum::Vorarbeiter->value);
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        $response = $this->getJson(self::ME_URL);
        $response->assertStatus(401);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson(self::LOGOUT_URL);
        $response->assertStatus(200);

        // Token should be revoked — subsequent call must fail
        $this->assertCount(0, $user->fresh()->tokens);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Validation
    // ──────────────────────────────────────────────────────────────────────

    public function test_api_login_validates_email_field(): void
    {
        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => 'not-valid',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_api_login_requires_password(): void
    {
        $response = $this->postJson(self::LOGIN_URL, [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Token abilities
    // ──────────────────────────────────────────────────────────────────────

    public function test_vorarbeiter_token_has_upload_ability(): void
    {
        $user = User::factory()->vorarbeiter()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);

        // Re-fetch from DB to get the freshly created token
        $user->refresh();
        $token = $user->tokens()->first();

        $this->assertNotNull($token, 'Token should have been created after login.');
        $this->assertTrue($token->can('jobs:upload'));
        $this->assertTrue($token->can('checklist:update'));
    }

    public function test_mitarbeiter_token_does_not_have_upload_ability(): void
    {
        $user = User::factory()->mitarbeiter()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $token = $user->tokens()->first();

        $this->assertNotNull($token, 'Token should have been created after login.');
        $this->assertFalse($token->can('jobs:upload'));
        $this->assertFalse($token->can('checklist:update'));
    }
}
