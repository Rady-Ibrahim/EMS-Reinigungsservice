<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    // ── Index ──────────────────────────────────────────────────────────────

    public function test_mitarbeiter_can_get_active_locations(): void
    {
        CustomerLocation::factory()->count(3)->create();
        CustomerLocation::factory()->inactive()->create(); // should be excluded

        $user = User::factory()->mitarbeiter()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/locations');
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_location_response_includes_required_fields(): void
    {
        CustomerLocation::factory()->create();

        $user = User::factory()->mitarbeiter()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/locations');
        $response->assertJsonStructure([
            'data' => [['id', 'name', 'customer', 'address', 'latitude', 'longitude']],
        ]);
    }

    // ── Data Isolation: security_code ─────────────────────────────────────

    public function test_security_code_is_never_in_api_response(): void
    {
        CustomerLocation::factory()->withSecurityCode()->create();

        $user = User::factory()->mitarbeiter()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/locations');
        $body     = json_decode($response->getContent(), true);

        foreach ($body['data'] as $loc) {
            $this->assertArrayNotHasKey('security_code', $loc);
        }
    }

    // ── Role-based field visibility ────────────────────────────────────────

    public function test_vorarbeiter_sees_access_instructions(): void
    {
        $loc = CustomerLocation::factory()->create(['access_instructions' => 'Schlüssel links']);

        $vorarbeiter = User::factory()->vorarbeiter()->create();
        Sanctum::actingAs($vorarbeiter);

        $response = $this->getJson('/api/v1/locations/' . $loc->id);
        $response->assertJsonPath('data.access_instructions', 'Schlüssel links');
    }

    public function test_mitarbeiter_does_NOT_see_access_instructions(): void
    {
        $loc = CustomerLocation::factory()->create(['access_instructions' => 'Schlüssel links']);

        $mitarbeiter = User::factory()->mitarbeiter()->create();
        Sanctum::actingAs($mitarbeiter);

        $response = $this->getJson('/api/v1/locations/' . $loc->id);
        $body = $response->json('data');

        $this->assertArrayNotHasKey('access_instructions', $body);
    }

    // ── Show inactive ──────────────────────────────────────────────────────

    public function test_inactive_location_returns_404(): void
    {
        $loc  = CustomerLocation::factory()->inactive()->create();
        $user = User::factory()->mitarbeiter()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/locations/' . $loc->id)->assertStatus(404);
    }

    // ── Authentication ─────────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/locations')->assertStatus(401);
    }
}
