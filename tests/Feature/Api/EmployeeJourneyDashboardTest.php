<?php

namespace Tests\Feature\Api;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraAuftragStatusEnum;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragAssignee;
use App\Models\TimeAdjustmentRequest;
use App\Models\TravelTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Full employee journey (End-to-End): a field worker runs the whole mobile
 * flow against the real HTTP kernel, and every step is asserted against the
 * admin dashboard — live status panel, monthly hours and the time-adjustment
 * inbox.
 *
 * Flow: login → device token → calendar (isolation) → travel/start →
 * GPS batch → arrive → [dashboard: Auf Anfahrt] → work/start →
 * [dashboard: Im Einsatz] → before photos + checklist → work-end →
 * leader complete → time adjustment → [dashboard: Feierabend + hours +
 * pending adjustment].
 */
class EmployeeJourneyDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $leader;

    private User $worker;

    private ExtraAuftrag $order;

    private int $foreignOrderId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create([
            'name'     => 'Admin Dashboard',
            'email'    => 'admin-e2e@ems-reinigungsservice.de',
            'password' => bcrypt('password'),
            'locale'   => 'de',
        ]);

        $this->leader = User::factory()->vorarbeiter()->create([
            'name'     => 'Vorarbeiter Schmidt',
            'email'    => 'leader-e2e@ems-reinigungsservice.de',
            'password' => bcrypt('password'),
            'locale'   => 'de',
        ]);

        $this->worker = User::factory()->mitarbeiter()->create([
            'name'     => 'Mitarbeiter Hassan',
            'email'    => 'worker-e2e@ems-reinigungsservice.de',
            'password' => bcrypt('password'),
            'locale'   => 'de',
        ]);

        $customer = Customer::factory()->create();
        $location = CustomerLocation::factory()->create([
            'customer_id' => $customer->id,
            'latitude'    => 49.1000,
            'longitude'   => 8.1000,
        ]);

        $this->order = ExtraAuftrag::factory()->create([
            'customer_id'          => $customer->id,
            'location_id'          => $location->id,
            'created_by'           => $this->admin->id,
            'title'                => 'E2E Grundreinigung Halle 7',
            'status'               => ExtraAuftragStatusEnum::Assigned,
            'scheduled_date'       => now()->toDateString(),
            'scheduled_time_start' => '08:00:00',
            'estimated_hours'      => 8.0,
            'is_travel_time_paid'  => true,
            'has_alarm_system'     => true,
            'has_key_safe'         => true,
            'checklist_template'   => [
                ['task' => 'Boden reinigen', 'completed' => false],
                ['task' => 'Fenster putzen', 'completed' => false],
                ['task' => 'Müll entsorgen', 'completed' => false],
            ],
        ]);

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $this->order->id,
            'user_id'          => $this->leader->id,
            'role_in_order'    => AssigneeRoleEnum::Leader,
        ]);
        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $this->order->id,
            'user_id'          => $this->worker->id,
            'role_in_order'    => AssigneeRoleEnum::Member,
        ]);

        // A second order visible to the leader only — the worker must never
        // see it (task isolation).
        $foreign = ExtraAuftrag::factory()->create([
            'customer_id'    => $customer->id,
            'location_id'    => $location->id,
            'created_by'     => $this->admin->id,
            'title'          => 'E2E Geheimauftrag Büro',
            'status'         => ExtraAuftragStatusEnum::Assigned,
            'scheduled_date' => now()->toDateString(),
        ]);
        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $foreign->id,
            'user_id'          => $this->leader->id,
            'role_in_order'    => AssigneeRoleEnum::Leader,
        ]);
        $this->foreignOrderId = $foreign->id;
    }

    /**
     * Authenticate the next mobile calls with the given Sanctum token.
     *
     * The auth manager (and each guard's resolved user) persists across
     * requests inside one test, so switching from one token to another
     * requires clearing the guard cache — otherwise the previous user
     * leaks into the next request.
     */
    private function apiAs(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    /**
     * The live status label rendered next to an employee in the admin
     * sidebar (same parsing as LiveStatusPanelTest).
     */
    private function liveStatus(string $name): ?string
    {
        $body = $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->getContent();

        // actingAs pins the web guard user for the rest of the test — clear
        // the resolved guards so subsequent mobile calls authenticate purely
        // from their Sanctum bearer token again.
        $this->app['auth']->forgetGuards();

        if (! preg_match(
            '/live-item.*?st-name">' . preg_quote($name, '/') . '<\/span>.*?st-label">([^<]+)</s',
            $body,
            $matches
        )) {
            return null;
        }

        return trim($matches[1]);
    }

    public function test_full_employee_journey_reflects_on_admin_dashboard(): void
    {
        $orderId = $this->order->id;
        $lat = 49.10005;
        $lng = 8.10005;

        // ── 1. Worker login (mobile) ──────────────────────────────────────
        $login = $this->postJson('/api/v1/auth/login', [
            'email'    => 'worker-e2e@ems-reinigungsservice.de',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.user.role', 'mitarbeiter');

        $workerToken = $login->json('data.token');
        $this->assertNotEmpty($workerToken);

        // ── 2. OneSignal device registration ──────────────────────────────
        $this->apiAs($workerToken)->postJson('/api/v1/device-tokens', [
            'token'       => 'onesignal_e2e_player_' . bin2hex(random_bytes(6)),
            'platform'    => 'android',
            'provider'    => 'onesignal',
            'device_name' => 'E2E Testgerät',
        ])->assertCreated();

        // ── 3. Calendar isolation: own order visible, foreign hidden ───────
        $calendar = $this->withToken($workerToken)
            ->getJson('/api/v1/calendar')
            ->assertOk();

        $ids = collect($calendar->json('data'))->pluck('id')->all();
        $this->assertContains("extra_auftrag:{$orderId}", $ids);
        $this->assertNotContains("extra_auftrag:{$this->foreignOrderId}", $ids);

        $this->withToken($workerToken)
            ->getJson("/api/v1/extra-orders/{$this->foreignOrderId}")
            ->assertNotFound();

        // ── 4. Start travel (deterministic 30 min ride) ────────────────────
        $departure = now()->subMinutes(35);
        $arrival = $departure->copy()->addMinutes(30);

        $travel = $this->apiAs($workerToken)->postJson(
            "/api/v1/extra-orders/{$orderId}/travel/start",
            [
                'gps_departure_lat' => $lat,
                'gps_departure_lng' => $lng,
                'departure_at'      => $departure->toIso8601String(),
            ]
        )->assertCreated();

        $trackId = $travel->json('data.id');
        $this->assertNotEmpty($trackId);

        // Live GPS points during the ride.
        $this->apiAs($workerToken)->postJson("/api/v1/travel-tracks/{$trackId}/gps/batch", [
            'points' => [
                ['latitude' => 49.10010, 'longitude' => 8.10010, 'recorded_at' => now()->subMinutes(25)->toIso8601String()],
                ['latitude' => 49.10020, 'longitude' => 8.10020, 'recorded_at' => now()->subMinutes(15)->toIso8601String()],
                ['latitude' => $lat, 'longitude' => $lng, 'recorded_at' => now()->subMinutes(5)->toIso8601String()],
            ],
        ])->assertCreated()->assertJsonPath('points_created', 3);

        // Dashboard: worker is on the way.
        $this->assertSame('Auf Anfahrt', $this->liveStatus('Mitarbeiter Hassan'));

        // ── 5. Arrive: travel time frozen, paid per order settings ─────────
        $this->apiAs($workerToken)->postJson(
            "/api/v1/extra-orders/{$orderId}/travel/arrive",
            [
                'gps_arrival_lat' => $lat,
                'gps_arrival_lng' => $lng,
                'arrival_at'      => $arrival->toIso8601String(),
            ]
        )->assertOk()->assertJsonPath('data.travel_minutes', 30);

        $track = TravelTrack::find($trackId);
        $this->assertSame(30, (int) $track->travel_minutes);
        $this->assertTrue((bool) $track->is_paid);

        // ── 6. Start work (deterministic 2h shift) ──────────────────────────
        $workStart = now()->subHours(2);

        $this->apiAs($workerToken)->postJson(
            "/api/v1/extra-orders/{$orderId}/work/start",
            [
                'gps_work_start_lat' => $lat,
                'gps_work_start_lng' => $lng,
                'work_start'         => $workStart->toIso8601String(),
            ]
        )->assertOk()->assertJsonPath('message', 'Arbeit gestartet.');

        // Dashboard: worker is on duty.
        $this->assertSame('Im Einsatz', $this->liveStatus('Mitarbeiter Hassan'));

        // ── 7. Field note (any assigned employee may append notes) ─────────
        $this->apiAs($workerToken)->postJson("/api/v1/auftraege/{$orderId}/notes", [
            'note' => 'E2E: Halle betreten, keine Besonderheiten am Eingang.',
        ])->assertOk()->assertJsonPath('message', 'Notiz gespeichert.');

        // NOTE: before/after photos + checklist are leader-only actions in
        // this backend (ExtraAuftragService::uploadBeforePhotos /
        // updateChecklist assert the leader role), so the Vorarbeiter
        // documents them below. A member calling the photo endpoint is
        // rejected — the permission boundary is pinned here.
        $this->apiAs($workerToken)->postJson("/api/v1/auftraege/{$orderId}/photos", [
            'stage'  => 'before',
            'photos' => ['uploads/e2e-member-1.jpg'],
        ])->assertForbidden();

        // ── 8. Leader documents + closes own execution ─────────────────────
        $leaderLogin = $this->postJson('/api/v1/auth/login', [
            'email'    => 'leader-e2e@ems-reinigungsservice.de',
            'password' => 'password',
        ])->assertOk();

        $leaderToken = $leaderLogin->json('data.token');

        $this->apiAs($leaderToken)->postJson(
            "/api/v1/extra-orders/{$orderId}/travel/start", []
        )->assertCreated();
        $this->apiAs($leaderToken)->postJson(
            "/api/v1/extra-orders/{$orderId}/travel/arrive", []
        )->assertOk();

        $leaderWorkStart = now()->subHour();

        $this->apiAs($leaderToken)->postJson(
            "/api/v1/extra-orders/{$orderId}/work/start",
            ['work_start' => $leaderWorkStart->toIso8601String()]
        )->assertOk();

        $this->apiAs($leaderToken)->postJson("/api/v1/auftraege/{$orderId}/photos", [
            'stage'  => 'before',
            'photos' => ['uploads/e2e-before-1.jpg', 'uploads/e2e-before-2.jpg'],
        ])->assertOk();

        $leaderExecutionId = $this->order->executions()->where('user_id', $this->leader->id)->first()->id;

        $this->apiAs($leaderToken)->patchJson("/api/v1/extra-executions/{$leaderExecutionId}/checklist", [
            'checklist_items' => [
                ['task' => 'Boden reinigen', 'completed' => true],
                ['task' => 'Fenster putzen', 'completed' => true],
                ['task' => 'Müll entsorgen', 'completed' => true],
            ],
        ])->assertOk();

        $leaderEnd = $this->apiAs($leaderToken)->postJson(
            "/api/v1/extra-orders/{$orderId}/work-end",
            [
                'key_returned'    => true,
                'windows_closed'  => true,
                'alarm_activated' => true,
                'shift_note'      => 'E2E: Abnahme durch Vorarbeiter.',
                'after_photos'    => ['uploads/e2e-after-1.jpg'],
            ]
        )->assertOk();

        // Leader: 60 min work, no paid travel (0 min ride) → 60 paid minutes.
        $this->assertSame(60, (int) $leaderEnd->json('data.work_minutes'));
        $this->assertSame(60, (int) $leaderEnd->json('data.paid_minutes'));

        // ── 9. Leader completes the whole order ────────────────────────────
        // This also freezes + completes the member execution left open.
        $this->apiAs($leaderToken)->postJson(
            "/api/v1/extra-orders/{$orderId}/complete", []
        )->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertSame(
            ExtraAuftragStatusEnum::Completed,
            $this->order->fresh()->status
        );

        // Member execution auto-frozen: 120 min work + 30 min paid travel.
        $workerExecution = $this->order->executions()->where('user_id', $this->worker->id)->first();
        $this->assertSame(120, (int) $workerExecution->work_minutes);
        $this->assertSame(150, (int) $workerExecution->paid_minutes);

        // ── 10. Time adjustment for today ──────────────────────────────────
        $this->apiAs($workerToken)->postJson('/api/v1/time-adjustments', [
            'job_type'        => 'extra_auftrag',
            'job_id'          => $orderId,
            'requested_start' => now()->subHours(3)->toIso8601String(),
            'requested_end'   => now()->subHour()->toIso8601String(),
            'reason'          => 'E2E: GPS-Start verspaetet, Beginn korrigieren.',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        // ── Dashboard assertions ───────────────────────────────────────────
        // a) Live status: shift is over → Feierabend.
        $this->assertSame('Feierabend', $this->liveStatus('Mitarbeiter Hassan'));

        // b) Monthly hours: travel + actual work are both counted.
        $summary = $this->apiAs($workerToken)->getJson(
            '/api/v1/time-summary?year=' . now()->year . '&month=' . now()->month
        )->assertOk();

        $this->assertSame(1, (int) $summary->json('data.extra_count'));
        $this->assertEqualsWithDelta(2.0, (float) $summary->json('data.extra_actual_hours'), 0.01);
        $this->assertEqualsWithDelta(2.5, (float) $summary->json('data.extra_paid_hours'), 0.01);

        $lineItems = $this->apiAs($workerToken)->getJson(
            '/api/v1/monthly-reports/line-items?year=' . now()->year . '&month=' . now()->month
        )->assertOk();

        $this->assertNotEmpty($lineItems->json('data'));

        // c) Time adjustment is pending in the admin inbox.
        $adjustment = TimeAdjustmentRequest::where('employee_id', $this->worker->id)->first();
        $this->assertNotNull($adjustment);
        $this->assertSame('pending', $adjustment->status->value);
        $this->assertNotNull($adjustment->original_start);
        $this->assertNotNull($adjustment->original_end);

        $this->actingAs($this->admin)
            ->get('/admin/time-adjustments')
            ->assertOk()
            ->assertSee('E2E: GPS-Start verspaetet, Beginn korrigieren.');
    }
}
