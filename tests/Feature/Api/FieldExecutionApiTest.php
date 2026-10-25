<?php

namespace Tests\Feature\Api;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Models\AdminNotification;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragAssignee;
use App\Models\ExtraAuftragExecution;
use App\Models\User;
use App\Services\AdminAlertEvaluator;
use App\Services\AuthService;
use App\Services\ExtraAuftragService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mobile field API: closing & hand-over, break, notes, and the enriched
 * task-detail payload.
 */
class FieldExecutionApiTest extends TestCase
{
    use RefreshDatabase;

    private ExtraAuftragService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ExtraAuftragService::class);
    }

    private function token(User $user): string
    {
        return app(AuthService::class)->createApiToken($user)['token'];
    }

    /**
     * Order with one leader, fully worked and ready to be closed.
     */
    private function readyToClose(User $leader, array $orderAttrs = []): ExtraAuftrag
    {
        $order = ExtraAuftrag::factory()->create(array_merge([
            'estimated_hours'   => 2.0,
            'has_alarm_system'  => true,
            'has_key_safe'      => true,
        ], $orderAttrs));

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $leader->id,
            'role_in_order'    => AssigneeRoleEnum::Leader,
        ]);

        $execution = ExtraAuftragExecution::create([
            'extra_auftrag_id'        => $order->id,
            'user_id'                 => $leader->id,
            'status'                  => ExtraExecutionStatusEnum::Working,
            'work_start'              => now()->subHours(2),
            // Started without GPS: the closing call is not forced to send a
            // position, and a location can be injected per test.
            'gps_work_start_lat'      => null,
            'gps_work_start_lng'      => null,
            'before_photos'           => ['before-1.jpg'],
            'after_photos'            => ['after-1.jpg'],
            'checklist_items'         => [
                ['task' => 'Boden reinigen', 'completed' => true],
            ],
        ]);

        $this->orderId    = $order->id;
        $this->executionId = $execution->id;

        return $order;
    }

    private int $orderId = 0;

    private int $executionId = 0;

    // ── work-end: security hand-over ───────────────────────────────────────

    public function test_work_end_persists_the_three_security_confirmations(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $response = $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'     => true,
                'windows_closed'   => true,
                'alarm_activated'  => true,
                'shift_note'       => 'Schlüssel im Tresor, Fenster zu.',
                'gps_work_end_lat' => 49.1001,
                'gps_work_end_lng' => 8.1001,
            ])
            ->assertOk();

        $response->assertJsonPath('data.key_returned', true)
                  ->assertJsonPath('data.windows_closed', true)
                  ->assertJsonPath('data.alarm_activated', true)
                  ->assertJsonPath('data.shift_note', 'Schlüssel im Tresor, Fenster zu.')
                  ->assertJsonPath('data.status', ExtraExecutionStatusEnum::Completed->value);

        $this->assertDatabaseHas('extra_auftrag_executions', [
            'id'              => $this->executionId,
            'key_returned'    => 1,
            'windows_closed'  => 1,
            'alarm_activated' => 1,
        ]);
    }

    public function test_regular_employee_can_end_own_work_without_leader_photos_or_checklist(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $worker = User::factory()->mitarbeiter()->create();
        $order  = $this->readyToClose($leader);

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $worker->id,
            'role_in_order'    => AssigneeRoleEnum::Member,
        ]);

        $execution = ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $worker->id,
            'status'           => ExtraExecutionStatusEnum::Working,
            'work_start'       => now()->subHour(),
            'checklist_items'  => [['task' => 'Boden reinigen', 'completed' => false]],
        ]);

        $this->withToken($this->token($worker))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ExtraExecutionStatusEnum::Completed->value);

        $this->assertSame([], $execution->fresh()->before_photos ?? []);
        $this->assertSame([], $execution->fresh()->after_photos ?? []);
        $this->assertFalse($execution->fresh()->isChecklistComplete());
    }

    public function test_leader_can_upload_private_order_photos_for_assigned_employees_to_view(): void
    {
        Storage::fake('local');

        $leader = User::factory()->vorarbeiter()->create();
        $worker = User::factory()->mitarbeiter()->create();
        $order  = $this->readyToClose($leader);

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $worker->id,
            'role_in_order'    => AssigneeRoleEnum::Member,
        ]);

        $response = $this->withToken($this->token($leader))
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/extra-orders/{$order->id}/photos/upload", [
                'stage'  => 'before',
                'photos' => [UploadedFile::fake()->image('field-before.png')],
            ])
            ->assertCreated();

        $execution = ExtraAuftragExecution::where('extra_auftrag_id', $order->id)
            ->where('user_id', $leader->id)
            ->firstOrFail();
        $photoPath = $execution->before_photos[1];
        $photoUrl = route('api.v1.extra-orders.photos.download', [$order->id, 'before', 1]);

        Storage::disk('local')->assertExists($photoPath);
        $response->assertJsonPath('data.before_photos.1', $photoUrl);

        $this->withToken($this->token($worker))
            ->get($photoUrl)
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $outsider = User::factory()->mitarbeiter()->create();
        $this->withToken($this->token($outsider))
            ->get($photoUrl)
            ->assertNotFound();
    }

    public function test_regular_employee_cannot_upload_order_photos(): void
    {
        Storage::fake('local');

        $leader = User::factory()->vorarbeiter()->create();
        $worker = User::factory()->mitarbeiter()->create();
        $order  = $this->readyToClose($leader);

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $worker->id,
            'role_in_order'    => AssigneeRoleEnum::Member,
        ]);

        $this->withToken($this->token($worker))
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/extra-orders/{$order->id}/photos/upload", [
                'stage'  => 'before',
                'photos' => [UploadedFile::fake()->image('unauthorized.png')],
            ])
            ->assertForbidden();

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_work_end_requires_every_security_confirmation(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
            ])
            ->assertOk();

        // A second close is rejected as already completed
        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('execution');
    }

    public function test_work_end_rejects_a_missing_security_field(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'   => true,
                'windows_closed' => true,
                // alarm_activated omitted on purpose
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('alarm_activated');
    }

    public function test_work_end_blocks_when_the_alarm_confirm_is_false(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => false, // site has an alarm, must be armed
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('alarm_activated');

        $this->assertDatabaseHas('extra_auftrag_executions', [
            'id' => $this->executionId, 'work_end' => null,
        ]);
    }

    public function test_site_without_alarm_does_not_require_the_alarm_confirmation(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader, [
            'has_alarm_system' => false,
            'has_key_safe'     => false,
        ]);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => false, // no key safe on site
                'windows_closed'  => true,
                'alarm_activated' => false, // no alarm on site
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ExtraExecutionStatusEnum::Completed->value);
    }

    public function test_work_end_blocks_on_missing_before_photos(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        ExtraAuftragExecution::where('id', $this->executionId)
            ->update(['before_photos' => null]);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('before_photos');
    }

    public function test_work_end_blocks_on_incomplete_checklist(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        ExtraAuftragExecution::where('id', $this->executionId)->update([
            'checklist_items' => [
                ['task' => 'Boden reinigen', 'completed' => true],
                ['task' => 'Fenster putzen',  'completed' => false],
            ],
        ]);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('checklist_items');
    }

    public function test_work_end_reports_all_missing_items_at_once(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        ExtraAuftragExecution::where('id', $this->executionId)->update([
            'before_photos'   => null,
            'after_photos'    => null,
            'checklist_items' => [['task' => 'Boden', 'completed' => false]],
        ]);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => false,
                'windows_closed'  => false,
                'alarm_activated' => false,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['before_photos', 'after_photos', 'checklist_items', 'windows_closed']);
    }

    public function test_shift_note_is_limited_to_200_characters(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
                'shift_note'      => str_repeat('x', 201),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('shift_note');
    }

    public function test_unassigned_employee_cannot_close_the_order(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);
        $outsider = User::factory()->mitarbeiter()->create();

        $this->withToken($this->token($outsider))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
            ])
            ->assertStatus(500); // RuntimeException, pre-existing service contract
    }

    // ── pause ──────────────────────────────────────────────────────────────

    public function test_pause_start_and_stop_are_recorded(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/pause", ['action' => 'start'])
            ->assertOk()
            ->assertJsonPath('data.is_paused', true);

        $this->assertDatabaseHas('extra_auftrag_executions', [
            'id' => $this->executionId, 'pause_ended_at' => null,
        ]);

        $this->travel(30)->minutes();

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/pause", ['action' => 'stop'])
            ->assertOk()
            ->assertJsonPath('data.is_paused', false);

        $execution = ExtraAuftragExecution::find($this->executionId);
        $this->assertSame(30, (int) $execution->pause_minutes);
    }

    public function test_pause_minutes_are_deducted_from_the_paid_working_time(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        // 120 min have passed since work_start
        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/pause", ['action' => 'start'])
            ->assertOk();

        $this->travel(30)->minutes();

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/pause", ['action' => 'stop'])
            ->assertOk();

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
            ])
            ->assertOk();

        $execution = ExtraAuftragExecution::find($this->executionId);

        // work_start −120min, pause ran 30min of the following 150min
        // 150 elapsed − 30 break = 120 paid working minutes
        $this->assertSame(120, (int) $execution->work_minutes);
        $this->assertSame(30, (int) $execution->pause_minutes);
    }

    public function test_pause_cannot_be_started_twice(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/pause", ['action' => 'start'])
            ->assertOk();

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/pause", ['action' => 'start'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pause');
    }

    public function test_pause_cannot_stop_when_none_is_running(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/pause", ['action' => 'stop'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pause');
    }

    public function test_pause_rejects_an_unknown_action(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/pause", ['action' => 'toggle'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('action');
    }

    // ── notes ──────────────────────────────────────────────────────────────

    public function test_note_is_appended_with_a_timestamp(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/notes", ['note' => 'Schlüssel lag im Büro'])
            ->assertOk();

        $execution = ExtraAuftragExecution::find($this->executionId);
        $this->assertStringContainsString('Schlüssel lag im Büro', $execution->employee_notes);
        $this->assertMatchesRegularExpression('/^\[\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}\]/', $execution->employee_notes);
    }

    public function test_second_note_keeps_the_first_one(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/notes", ['note' => 'Erste Notiz'])
            ->assertOk();

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/notes", ['note' => 'Zweite Notiz'])
            ->assertOk();

        $notes = ExtraAuftragExecution::find($this->executionId)->employee_notes;

        $this->assertStringContainsString('Erste Notiz', $notes);
        $this->assertStringContainsString('Zweite Notiz', $notes);
    }

    public function test_note_is_required(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/notes", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('note');
    }

    // ── task detail enrichment ─────────────────────────────────────────────

    public function test_task_detail_includes_vehicle_equipment_and_security(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader, [
            'vehicle_label'   => 'Sprinter · BM-XY 1234',
            'equipment_info'  => [
                ['name' => 'Staubsauger', 'asset_id' => 'ST-01'],
                ['name' => 'Wischmopp',   'asset_id' => 'WM-02'],
            ],
            'has_alarm_system' => true,
            'has_key_safe'     => true,
        ]);

        $this->withToken($this->token($leader))
            ->getJson("/api/v1/extra-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.vehicle_info.label', 'Sprinter · BM-XY 1234')
            ->assertJsonPath('data.security.has_alarm_system', true)
            ->assertJsonPath('data.security.has_key_safe', true)
            ->assertJsonCount(2, 'data.equipment_info')
            ->assertJsonPath('data.equipment_info.0.name', 'Staubsauger');
    }

    public function test_task_detail_never_exposes_financial_fields(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader, ['price' => 999.99]);

        $body = $this->withToken($this->token($leader))
            ->getJson("/api/v1/extra-orders/{$order->id}")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('price', $body);
        $this->assertStringNotContainsString('999', $body);
    }

    public function test_execution_payload_exposes_the_security_state(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        ExtraAuftragExecution::where('id', $this->executionId)->update([
            'key_returned'   => true,
            'windows_closed' => false,
            'shift_note'     => 'Kurz notiert',
        ]);

        $this->withToken($this->token($leader))
            ->getJson("/api/v1/extra-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.execution.key_returned', true)
            ->assertJsonPath('data.execution.windows_closed', false)
            ->assertJsonPath('data.execution.shift_note', 'Kurz notiert')
            ->assertJsonPath('data.execution.is_paused', false);
    }

    // ── alerts ─────────────────────────────────────────────────────────────

    public function test_blocked_close_raises_a_checklist_alert(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        ExtraAuftragExecution::where('id', $this->executionId)->update([
            'checklist_items' => [['task' => 'Boden', 'completed' => false]],
        ]);

        // Trigger a refused close
        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
            ])
            ->assertStatus(422);

        $created = app(AdminAlertEvaluator::class)->evaluateBlockedCloses();

        $this->assertGreaterThanOrEqual(1, $created);
        $this->assertDatabaseHas('admin_notifications', [
            'dedupe_key' => 'checklist_blocked_close:'.$order->id,
        ]);
    }

    public function test_blocked_close_alert_is_suppressed_once_the_gap_is_closed(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        ExtraAuftragExecution::where('id', $this->executionId)->update([
            'checklist_items' => [['task' => 'Boden', 'completed' => false]],
        ]);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned' => true, 'windows_closed' => true, 'alarm_activated' => true,
            ])
            ->assertStatus(422);

        // Employee finishes the checklist afterwards
        ExtraAuftragExecution::where('id', $this->executionId)->update([
            'checklist_items' => [['task' => 'Boden', 'completed' => true]],
        ]);

        $created = app(AdminAlertEvaluator::class)->evaluateBlockedCloses();

        $this->assertSame(0, $created, 'A closed gap must not raise an alert.');
    }

    public function test_geofence_deviation_at_work_end_raises_an_alert(): void
    {
        config(['alerts.geofence_radius_meters' => 150]);

        $leader = User::factory()->vorarbeiter()->create();

        $order = $this->readyToClose($leader, [
            'location_id' => \App\Models\CustomerLocation::factory()->create([
                'latitude'  => 49.10000000,
                'longitude' => 8.10000000,
            ])->id,
        ]);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'   => true,
                'windows_closed' => true,
                'alarm_activated'=> true,
                // ~1.1 km away
                'gps_work_end_lat' => 49.11000000,
                'gps_work_end_lng' => 8.10000000,
            ])
            ->assertOk();

        $this->assertDatabaseHas('admin_notifications', [
            'dedupe_key' => 'geofence_deviation:'.$order->id.':'.$this->executionId.':end',
        ]);
    }

    public function test_gps_inside_the_geofence_raises_no_alert(): void
    {
        config(['alerts.geofence_radius_meters' => 150]);

        $leader = User::factory()->vorarbeiter()->create();

        $order = $this->readyToClose($leader, [
            'location_id' => \App\Models\CustomerLocation::factory()->create([
                'latitude'  => 49.10000000,
                'longitude' => 8.10000000,
            ])->id,
        ]);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
                'gps_work_end_lat' => 49.10010000, // ~11 m
                'gps_work_end_lng' => 8.10000000,
            ])
            ->assertOk();

        $this->assertDatabaseMissing('admin_notifications', [
            'dedupe_key' => 'geofence_deviation:'.$order->id.':'.$this->executionId.':end',
        ]);
    }

    public function test_geofence_alert_is_deduplicated(): void
    {
        config(['alerts.geofence_radius_meters' => 150]);

        $leader = User::factory()->vorarbeiter()->create();

        $order = $this->readyToClose($leader, [
            'location_id' => \App\Models\CustomerLocation::factory()->create([
                'latitude'  => 49.10000000,
                'longitude' => 8.10000000,
            ])->id,
        ]);

        $far = [
            'key_returned'   => true,
            'windows_closed' => true,
            'alarm_activated'=> true,
            'gps_work_end_lat' => 49.11000000,
            'gps_work_end_lng' => 8.10000000,
        ];

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work-end", $far)
            ->assertOk();

        $count = AdminNotification::where('dedupe_key', 'geofence_deviation:'.$order->id.':'.$this->executionId.':end')->count();

        $this->assertSame(1, $count, 'The same deviation must not alert twice.');
    }

    public function test_geofence_deviation_is_flagged_at_work_start(): void
    {
        config(['alerts.geofence_radius_meters' => 150]);

        $leader = User::factory()->vorarbeiter()->create();

        $order = ExtraAuftrag::factory()->create([
            'location_id' => \App\Models\CustomerLocation::factory()->create([
                'latitude'  => 49.10000000,
                'longitude' => 8.10000000,
            ])->id,
        ]);

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $leader->id,
            'role_in_order'    => AssigneeRoleEnum::Leader,
        ]);

        $execution = ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $leader->id,
            'status'           => ExtraExecutionStatusEnum::Arrived,
        ]);

        $this->orderId     = $order->id;
        $this->executionId = $execution->id;

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/extra-orders/{$order->id}/work/start", [
                'gps_work_start_lat' => 49.11000000,
                'gps_work_start_lng' => 8.10000000,
            ])
            ->assertOk();

        $this->assertDatabaseHas('admin_notifications', [
            'dedupe_key' => 'geofence_deviation:'.$order->id.':'.$execution->id.':start',
        ]);
    }

    public function test_after_photos_may_travel_with_the_closing_call(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        ExtraAuftragExecution::where('id', $this->executionId)->update(['after_photos' => []]);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/auftraege/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
                'after_photos'    => ['after-upload-1.jpg'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('extra_auftrag_executions', [
            'id'          => $this->executionId,
            'status'      => ExtraExecutionStatusEnum::Completed->value,
        ]);

        $execution = ExtraAuftragExecution::find($this->executionId);

        $this->assertSame(['after-upload-1.jpg'], $execution->after_photos);
    }

    public function test_work_end_is_also_reachable_under_the_auftraege_alias(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = $this->readyToClose($leader);

        $this->withToken($this->token($leader))
            ->postJson("/api/v1/auftraege/{$order->id}/work-end", [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('extra_auftrag_executions', [
            'id'     => $this->executionId,
            'status' => ExtraExecutionStatusEnum::Completed->value,
        ]);
    }
}
