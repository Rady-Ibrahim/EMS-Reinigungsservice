<?php

namespace Tests\Unit\Services;

use App\Enums\NotificationTypeEnum;
use App\Models\DeviceToken;
use App\Models\ExtraAuftrag;
use App\Models\User;
use App\Notifications\JobAssignedNotification;
use App\Services\NotificationEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OneSignalPushTest extends TestCase
{
    use RefreshDatabase;

    private const API_URL = 'https://api.onesignal.com/notifications';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'push.enabled' => true,
            'push.onesignal.app_id' => 'app-123',
            'push.onesignal.rest_api_key' => 'rest-abc',
            'push.onesignal.api_url' => self::API_URL,
        ]);
    }

    private function register(User $user, string $token, string $provider = 'onesignal'): DeviceToken
    {
        return DeviceToken::create([
            'user_id' => $user->id,
            'platform' => 'android',
            'provider' => $provider,
            'token' => $token,
            'last_used_at' => now(),
            'is_active' => true,
        ]);
    }

    public function test_engine_delivers_to_the_stored_player_id(): void
    {
        Http::fake([self::API_URL => Http::response(['id' => 'n1'], 200)]);

        $user = User::factory()->mitarbeiter()->create();
        $this->register($user, 'player-id-1');

        app(NotificationEngineService::class)->sendToUser(
            $user,
            NotificationTypeEnum::JobAssigned,
            'Neuer Auftrag',
            'Du wurdest eingeteilt.',
            ['auftrag_id' => 7]
        );

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === self::API_URL
                && $request->hasHeader('Authorization', 'Rest rest-abc')
                && $body['app_id'] === 'app-123'
                && $body['include_player_ids'] === ['player-id-1']
                && $body['contents'] === ['de' => 'Du wurdest eingeteilt.']
                && $body['data']['type'] === 'job_assigned'
                && $body['data']['auftrag_id'] === 7;
        });
    }

    public function test_headings_use_the_employees_own_locale(): void
    {
        Http::fake([self::API_URL => Http::response(['id' => 'n1'], 200)]);

        $user = User::factory()->mitarbeiter()->create(['locale' => 'ar']);
        $this->register($user, 'player-id-ar');

        app(NotificationEngineService::class)->sendToUser($user, NotificationTypeEnum::System, 'Wichtig', 'Bitte lesen.');

        Http::assertSent(fn (Request $r) => $r->data()['headings'] === ['ar' => 'Wichtig']);
    }

    public function test_push_is_a_noop_when_disabled_but_database_still_lands(): void
    {
        config(['push.enabled' => false]);
        Http::fake();

        $user = User::factory()->mitarbeiter()->create();
        $this->register($user, 'player-id-1');

        app(NotificationEngineService::class)->sendToUser($user, NotificationTypeEnum::System, 'Titel', 'Text');

        Http::assertNothingSent();
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'title' => 'Titel']);
    }

    public function test_inactive_and_foreign_providers_are_skipped(): void
    {
        Http::fake([self::API_URL => Http::response(['id' => 'n1'], 200)]);

        $user = User::factory()->mitarbeiter()->create();
        $this->register($user, 'inactive-one');
        DeviceToken::where('token', 'inactive-one')->update(['is_active' => false]);
        $this->register($user, 'webpush-one', 'webpush');

        app(NotificationEngineService::class)->sendToUser($user, NotificationTypeEnum::System, 'Titel');

        Http::assertNothingSent();
    }

    public function test_unknown_player_id_is_deactivated(): void
    {
        Http::fake([self::API_URL => Http::response(
            ['errors' => ['Invalid player_id: 0000-1111']],
            400
        )]);

        $user = User::factory()->mitarbeiter()->create();
        $device = $this->register($user, 'stale-player-id');

        app(NotificationEngineService::class)->sendToUser($user, NotificationTypeEnum::System, 'Titel');

        $this->assertFalse($device->fresh()->is_active);
    }

    public function test_success_touches_last_used_at(): void
    {
        Http::fake([self::API_URL => Http::response(['id' => 'n1'], 200)]);

        $user = User::factory()->mitarbeiter()->create();
        $device = $this->register($user, 'player-id-1');
        $device->update(['last_used_at' => now()->subDays(3)]);

        app(NotificationEngineService::class)->sendToUser($user, NotificationTypeEnum::System, 'Titel');

        $this->assertTrue($device->fresh()->last_used_at->isAfter(now()->subDay()));
    }

    public function test_route_notification_for_onesignal_returns_active_player_ids(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $this->register($user, 'player-a');
        $this->register($user, 'player-b');
        $this->register($user, 'player-webpush', 'webpush');
        $this->register($user, 'player-retired');
        DeviceToken::where('token', 'player-retired')->update(['is_active' => false]);

        $ids = $user->fresh()->routeNotificationForOneSignal();

        sort($ids);
        $this->assertSame(['player-a', 'player-b'], $ids);
    }

    public function test_job_assigned_notification_fans_out_to_every_device(): void
    {
        Http::fake([self::API_URL => Http::response(['id' => 'n1'], 200)]);

        $user = User::factory()->mitarbeiter()->create();
        $order = ExtraAuftrag::factory()->create(['title' => 'Büroreinigung']);

        $this->register($user, 'player-a');
        $this->register($user, 'player-b');

        Notification::send($user, new JobAssignedNotification($order));

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r->data()['data']['auftrag_id'] === $order->id);
    }

    public function test_job_assigned_notification_is_a_noop_without_devices(): void
    {
        Http::fake();

        $user = User::factory()->mitarbeiter()->create();
        $order = ExtraAuftrag::factory()->create();

        Notification::send($user, new JobAssignedNotification($order));

        Http::assertNothingSent();
    }
}
