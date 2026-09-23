<?php

namespace Tests\Feature\Api;

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function actingWithToken(User $user): self
    {
        $token = app(AuthService::class)->createApiToken($user)['token'];

        return $this->withToken($token);
    }

    public function test_employee_can_register_device_token(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($employee);

        $this->postJson('/api/v1/device-tokens', [
            'token'       => 'fcm-token-abc',
            'platform'    => 'android',
            'device_name' => 'Samsung S24',
        ])->assertCreated()
          ->assertJsonPath('message', 'Gerät registriert.');

        $this->assertDatabaseHas('device_tokens', [
            'user_id'     => $employee->id,
            'token'       => 'fcm-token-abc',
            'platform'    => 'android',
            'is_active'   => true,
        ]);
    }

    public function test_device_token_rejects_invalid_platform(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($employee);

        $this->postJson('/api/v1/device-tokens', [
            'token'    => 'x',
            'platform' => 'smartwatch',
        ])->assertUnprocessable();
    }

    public function test_employee_can_delete_own_device_token(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($employee);

        $device = DeviceToken::create([
            'user_id' => $employee->id,
            'platform'=> 'android',
            'provider'=> 'fcm',
            'token'   => 'delete-me',
        ]);

        $this->deleteJson("/api/v1/device-tokens/{$device->id}")
             ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['id' => $device->id]);
    }

    public function test_employee_lists_own_notifications(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($employee);

        UserNotification::create([
            'user_id' => $employee->id,
            'type'    => 'job_assigned',
            'title'   => 'Neuer Auftrag',
            'message' => 'Du wurdest eingeteilt.',
        ]);
        UserNotification::create([
            'user_id' => $employee->id,
            'type'    => 'system',
            'title'   => 'Wichtige Info',
        ]);

        $this->getJson('/api/v1/notifications')
             ->assertOk()
             ->assertJsonCount(2, 'data')
             ->assertJsonPath('unread', 2);
    }

    public function test_employee_can_mark_own_notification_read(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($employee);

        $notification = UserNotification::create([
            'user_id' => $employee->id,
            'type'    => 'system',
            'title'   => 'Info',
        ]);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")
             ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_employee_cannot_mark_foreign_notification(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $other    = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($employee);

        $foreign = UserNotification::create([
            'user_id' => $other->id,
            'type'    => 'system',
            'title'   => 'Fremd',
        ]);

        $this->postJson("/api/v1/notifications/{$foreign->id}/read")
             ->assertForbidden();
    }

    public function test_employee_can_mark_all_read(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($employee);

        UserNotification::create(['user_id' => $employee->id, 'type' => 'system', 'title' => 'A']);
        UserNotification::create(['user_id' => $employee->id, 'type' => 'alert', 'title' => 'B']);

        $this->postJson('/api/v1/notifications/read-all')
             ->assertOk()
             ->assertJsonPath('updated', 2);

        $this->getJson('/api/v1/notifications')->assertJsonPath('unread', 0);
    }

    public function test_api_requires_token_for_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }
}