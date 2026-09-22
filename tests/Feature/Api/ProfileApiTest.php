<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    // ── GET /api/v1/profile ────────────────────────────────────────────────

    public function test_employee_can_view_own_profile(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'role', 'locale', 'profile'],
            ])
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_profile_never_exposes_hourly_rate(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $user->employeeProfile->update(['hourly_rate' => '15.00']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');
        $body = $response->json();

        $this->assertArrayNotHasKey('hourly_rate', $body['data']['profile'] ?? []);
    }

    public function test_profile_never_exposes_iban(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $user->employeeProfile->update(['iban' => 'DE89370400440532013000']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');
        $body = $response->json();

        $this->assertArrayNotHasKey('iban', $body['data']['profile'] ?? []);
    }

    // ── PATCH /api/v1/profile ─────────────────────────────────────────────

    public function test_employee_can_update_locale(): void
    {
        $user = User::factory()->mitarbeiter()->create(['locale' => 'de']);
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/v1/profile', ['locale' => 'ar']);
        $response->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'ar']);
    }

    public function test_employee_can_update_phone(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', ['phone' => '+4917600001234']);
        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $user->id,
            'phone'   => '+4917600001234',
        ]);
    }

    public function test_locale_update_validates_allowed_values(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/v1/profile', ['locale' => 'fr']); // unsupported
        $response->assertStatus(422);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/profile');
        $response->assertStatus(401);
    }
}
