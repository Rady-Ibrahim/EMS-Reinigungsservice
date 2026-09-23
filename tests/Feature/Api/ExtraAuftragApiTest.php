<?php

namespace Tests\Feature\Api;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraAuftragStatusEnum;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragAssignee;
use App\Models\TravelTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExtraAuftragApiTest extends TestCase
{
    use RefreshDatabase;

    // ── Index ──────────────────────────────────────────────────────────────

    public function test_employee_sees_only_their_assigned_orders(): void
    {
        $leader  = User::factory()->vorarbeiter()->create();
        $other   = User::factory()->mitarbeiter()->create();

        $order1 = ExtraAuftrag::factory()->create();
        $order2 = ExtraAuftrag::factory()->create();

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order1->id,
            'user_id'          => $leader->id,
            'role_in_order'    => AssigneeRoleEnum::Leader,
        ]);
        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order2->id,
            'user_id'          => $other->id,
            'role_in_order'    => AssigneeRoleEnum::Member,
        ]);

        Sanctum::actingAs($leader);
        $response = $this->getJson('/api/v1/extra-orders');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data');
    }

    // ── Financial data isolation ──────────────────────────────────────────

    public function test_financial_data_never_exposed_in_api_response(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = ExtraAuftrag::factory()->withFinancials(800, 450)->create();

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $leader->id,
            'role_in_order'    => AssigneeRoleEnum::Leader,
        ]);

        Sanctum::actingAs($leader);
        $data = $this->getJson('/api/v1/extra-orders')->json('data.0');

        $this->assertArrayNotHasKey('price',         $data);
        $this->assertArrayNotHasKey('internal_cost', $data);
        $this->assertArrayNotHasKey('internal_notes',$data);
    }

    // ── Travel: full workflow ─────────────────────────────────────────────

    public function test_employee_can_start_travel_and_arrive(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $order    = ExtraAuftrag::factory()->withTravelTimePaid()->create();

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $employee->id,
            'role_in_order'    => AssigneeRoleEnum::Member,
        ]);

        Sanctum::actingAs($employee);

        // Start travel
        $this->postJson("/api/v1/extra-orders/{$order->id}/travel/start", [
            'gps_departure_lat' => 48.137154,
            'gps_departure_lng' => 11.576124,
        ])->assertStatus(201)
          ->assertJsonPath('data.is_paid', false);

        // Record arrival
        $response = $this->postJson("/api/v1/extra-orders/{$order->id}/travel/arrive", [
            'gps_arrival_lat' => 48.200000,
            'gps_arrival_lng' => 11.600000,
        ])->assertStatus(200);

        $track = $response->json('data');

        $this->assertNotNull($track['travel_minutes']);
        $this->assertTrue($track['is_paid']); // is_travel_time_paid = true on order
    }

    public function test_travel_minutes_frozen_regardless_of_order_setting_change(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $order    = ExtraAuftrag::factory()->withTravelTimePaid()->create();

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $employee->id,
            'role_in_order'    => AssigneeRoleEnum::Member,
        ]);

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/extra-orders/{$order->id}/travel/start");
        $this->postJson("/api/v1/extra-orders/{$order->id}/travel/arrive");

        // Admin changes setting AFTER arrival
        $order->update(['is_travel_time_paid' => false]);

        // Travel track is_paid should still be TRUE (frozen at arrival)
        $track = TravelTrack::where('extra_auftrag_id', $order->id)
                             ->where('user_id', $employee->id)
                             ->first();

        $this->assertTrue($track->is_paid, 'is_paid must be frozen at arrival time');
    }

    // ── Mandatory Vorarbeiter Validation ──────────────────────────────────

    public function test_order_cannot_be_created_without_leader(): void
    {
        $service = app(\App\Services\ExtraAuftragService::class);

        $customer = \App\Models\Customer::factory()->create();
        $location = \App\Models\CustomerLocation::factory()->create(['customer_id' => $customer->id]);
        $admin    = User::factory()->administrator()->create();
        $member   = User::factory()->mitarbeiter()->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->create([
            'customer_id'   => $customer->id,
            'location_id'   => $location->id,
            'created_by'    => $admin->id,
            'title'         => 'Test Order',
            'order_type'    => 'other',
            'scheduled_date'=> now()->toDateString(),
        ], [
            ['user_id' => $member->id, 'role_in_order' => 'member'], // no leader!
        ]);
    }

    public function test_order_created_successfully_with_leader(): void
    {
        $service  = app(\App\Services\ExtraAuftragService::class);
        $customer = \App\Models\Customer::factory()->create();
        $location = \App\Models\CustomerLocation::factory()->create(['customer_id' => $customer->id]);
        $admin    = User::factory()->administrator()->create();
        $leader   = User::factory()->vorarbeiter()->create();
        $member   = User::factory()->mitarbeiter()->create();

        $order = $service->create([
            'customer_id'    => $customer->id,
            'location_id'    => $location->id,
            'created_by'     => $admin->id,
            'title'          => 'Test Grundreinigung',
            'order_type'     => 'deep_clean',
            'scheduled_date' => now()->toDateString(),
        ], [
            ['user_id' => $leader->id, 'role_in_order' => 'leader'],
            ['user_id' => $member->id, 'role_in_order' => 'member'],
        ]);

        $this->assertEquals(2, $order->assignees()->count());
        $this->assertTrue($order->hasLeader());
        $this->assertEquals(ExtraAuftragStatusEnum::Assigned, $order->status);
    }

    // ── Leader-only actions ───────────────────────────────────────────────

    public function test_member_cannot_close_order(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $member = User::factory()->mitarbeiter()->create();
        $order  = ExtraAuftrag::factory()->withEmptyChecklist()->create();

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $leader->id,
            'role_in_order'    => AssigneeRoleEnum::Leader,
        ]);
        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $member->id,
            'role_in_order'    => AssigneeRoleEnum::Member,
        ]);

        Sanctum::actingAs($member);

        $this->expectException(\RuntimeException::class);

        $service = app(\App\Services\ExtraAuftragService::class);
        $service->completeOrder($order, $member->id, []);
    }

    // ── Closure validation: photos + checklist required ───────────────────

    public function test_order_cannot_close_without_before_photos(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = ExtraAuftrag::factory()->withEmptyChecklist()->create();

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $leader->id,
            'role_in_order'    => AssigneeRoleEnum::Leader,
        ]);

        // Create execution WITHOUT before_photos
        \App\Models\ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $leader->id,
            'status'           => \App\Enums\ExtraExecutionStatusEnum::Working,
            'before_photos'    => null,
            'after_photos'     => ['photo1.jpg'],
            'checklist_items'  => [],
        ]);

        $service = app(\App\Services\ExtraAuftragService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->completeOrder($order, $leader->id, []);
    }

    public function test_order_cannot_close_with_incomplete_checklist(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = ExtraAuftrag::factory()->create();

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $leader->id,
            'role_in_order'    => AssigneeRoleEnum::Leader,
        ]);

        // Execution with checklist not fully completed
        \App\Models\ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $leader->id,
            'status'           => \App\Enums\ExtraExecutionStatusEnum::Working,
            'before_photos'    => ['before.jpg'],
            'after_photos'     => ['after.jpg'],
            'checklist_items'  => [
                ['task' => 'Task 1', 'completed' => true],
                ['task' => 'Task 2', 'completed' => false], // incomplete
            ],
        ]);

        $service = app(\App\Services\ExtraAuftragService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->completeOrder($order, $leader->id, []);
    }

    // ── Travel time calculation ───────────────────────────────────────────

    public function test_unpaid_travel_not_counted_in_paid_minutes(): void
    {
        $calculator = app(\App\Services\TravelTimeCalculatorService::class);

        $order = ExtraAuftrag::factory()->create(['is_travel_time_paid' => false]);

        $track = new TravelTrack([
            'travel_minutes' => 45,
            'is_paid'        => false,
        ]);

        $execution = new \App\Models\ExtraAuftragExecution([
            'work_minutes' => 120,
        ]);

        $paidMinutes = $calculator->calculatePaidMinutes($execution, $track);
        $this->assertEquals(120, $paidMinutes);
    }

    public function test_paid_travel_adds_to_paid_minutes(): void
    {
        $calculator = app(\App\Services\TravelTimeCalculatorService::class);

        $track = new TravelTrack([
            'travel_minutes' => 45,
            'is_paid'        => true,
        ]);

        $execution = new \App\Models\ExtraAuftragExecution([
            'work_minutes' => 120,
        ]);

        $paidMinutes = $calculator->calculatePaidMinutes($execution, $track);
        $this->assertEquals(165, $paidMinutes); // 120 + 45
    }

    public function test_return_trip_never_counted(): void
    {
        // Return trip logic: only one TravelTrack per employee — no return tracking
        $calculator = app(\App\Services\TravelTimeCalculatorService::class);

        $track = new TravelTrack([
            'travel_minutes' => 60, // outbound only
            'is_paid'        => true,
        ]);

        $execution = new \App\Models\ExtraAuftragExecution([
            'work_minutes' => 180,
        ]);

        $paidMinutes = $calculator->calculatePaidMinutes($execution, $track);

        // Only outbound 60 minutes added — no return
        $this->assertEquals(240, $paidMinutes);
        $this->assertEquals(4.0, $calculator->minutesToHours($paidMinutes));
    }
}
