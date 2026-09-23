<?php

namespace Tests\Unit\Services;

use App\Enums\AdminNotificationTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\AdminNotification;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationEngineService $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(NotificationEngineService::class);
    }

    public function test_send_to_user_creates_database_row(): void
    {
        $user = User::factory()->mitarbeiter()->create();

        $row = $this->engine->sendToUser($user, NotificationTypeEnum::JobAssigned,
            'Neuer Auftrag', 'Message', ['auftrag_id' => 5]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type'    => 'job_assigned',
            'title'   => 'Neuer Auftrag',
        ]);
        $this->assertEquals('Message', $row->message);
        $this->assertNull($row->read_at);
    }

    public function test_send_to_user_with_string_type_works(): void
    {
        $user = User::factory()->mitarbeiter()->create();

        $this->engine->sendToUser($user, 'custom_type', 'Titel');

        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'custom_type']);
    }

    public function test_notify_admins_delegates_with_dedupe_key(): void
    {
        $row = $this->engine->notifyAdmins(AdminNotificationTypeEnum::System, 'Titel', 'Meldung', [], 'k1');

        $this->assertInstanceOf(AdminNotification::class, $row);
        $this->assertEquals('k1', $row->dedupe_key);
        $this->assertDatabaseHas('admin_notifications', ['dedupe_key' => 'k1', 'is_read' => false]);
    }

    public function test_mark_read_sets_read_at(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $row  = UserNotification::create([
            'user_id' => $user->id,
            'type'    => 'system',
            'title'   => 'Titel',
        ]);

        $this->engine->markRead($row, $user);

        $this->assertNotNull($row->fresh()->read_at);
    }

    public function test_mark_read_of_foreign_notification_forbidden(): void
    {
        $owner = User::factory()->mitarbeiter()->create();
        $other = User::factory()->mitarbeiter()->create();

        $row = UserNotification::create([
            'user_id' => $owner->id,
            'type'    => 'system',
            'title'   => 'Titel',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->engine->markRead($row, $other);
    }

    public function test_mark_all_read(): void
    {
        $user = User::factory()->mitarbeiter()->create();

        UserNotification::create(['user_id' => $user->id, 'type' => 'alert', 'title' => 'A']);
        UserNotification::create(['user_id' => $user->id, 'type' => 'alert', 'title' => 'B']);

        $updated = $this->engine->markAllRead($user);

        $this->assertEquals(2, $updated);
        $this->assertEquals(0, $this->engine->unreadCountFor($user));
    }
}