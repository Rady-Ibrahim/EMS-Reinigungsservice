<?php

namespace Tests\Feature\Api;

use App\Enums\ExecutionStatusEnum;
use App\Enums\FixFrequencyEnum;
use App\Models\FixObject;
use App\Models\FixObjectAssignment;
use App\Models\FixObjectSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FixObjectApiTest extends TestCase
{
    use RefreshDatabase;

    // ── Index: employee sees only their fix objects ───────────────────────

    public function test_employee_sees_only_assigned_fix_objects(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $other    = User::factory()->mitarbeiter()->create();

        $fo1 = FixObject::factory()->create();
        $fo2 = FixObject::factory()->create(); // not assigned to $employee

        FixObjectAssignment::create([
            'fix_object_id' => $fo1->id,
            'user_id'       => $employee->id,
            'assigned_from' => now()->subDay()->toDateString(),
        ]);
        FixObjectAssignment::create([
            'fix_object_id' => $fo2->id,
            'user_id'       => $other->id,
            'assigned_from' => now()->subDay()->toDateString(),
        ]);

        Sanctum::actingAs($employee);
        $response = $this->getJson('/api/v1/fix-objects');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.id', $fo1->id);
    }

    // ── Financial data isolation ──────────────────────────────────────────

    public function test_financial_data_never_exposed_in_api(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fo = FixObject::factory()->withFinancials(800, 450)->create();

        FixObjectAssignment::create([
            'fix_object_id' => $fo->id,
            'user_id'       => $employee->id,
            'assigned_from' => now()->subDay()->toDateString(),
        ]);

        Sanctum::actingAs($employee);
        $response = $this->getJson('/api/v1/fix-objects');
        $data = $response->json('data.0');

        $this->assertArrayNotHasKey('price_per_month', $data);
        $this->assertArrayNotHasKey('price_per_hour',  $data);
        $this->assertArrayNotHasKey('internal_cost',   $data);
        $this->assertArrayNotHasKey('profit_margin',   $data);
        $this->assertArrayNotHasKey('internal_notes',  $data);
    }

    // ── Employee cannot see unassigned fix object ─────────────────────────

    public function test_employee_cannot_view_unassigned_fix_object(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fo = FixObject::factory()->create();

        Sanctum::actingAs($employee);
        $this->getJson("/api/v1/fix-objects/{$fo->id}")->assertStatus(404);
    }

    // ── Schedules: employee sees only their own ───────────────────────────

    public function test_employee_sees_only_own_schedules(): void
    {
        $emp1 = User::factory()->mitarbeiter()->create();
        $emp2 = User::factory()->mitarbeiter()->create();
        $fo   = FixObject::factory()->create();

        FixObjectAssignment::create(['fix_object_id' => $fo->id, 'user_id' => $emp1->id, 'assigned_from' => now()->subDay()->toDateString()]);

        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id, 'scheduled_date' => now()->toDateString()]);

        // emp2 execution — should not appear in emp1 API response
        \App\Models\FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $emp2->id,
            'actual_start'           => now(),
            'status'                 => ExecutionStatusEnum::Started,
            'contract_hours_applied' => 2.0,
        ]);

        Sanctum::actingAs($emp1);
        $response = $this->getJson("/api/v1/fix-objects/{$fo->id}/schedules?from=" . now()->toDateString() . "&to=" . now()->toDateString());

        $response->assertStatus(200);
        $scheduleData = $response->json('data.0');

        // Execution should be null because emp1 hasn't started theirs
        $this->assertNull($scheduleData['execution']);
    }

    // ── Execution workflow ────────────────────────────────────────────────

    public function test_employee_can_start_execution(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fo       = FixObject::factory()->withContractHours(2.5)->create();

        FixObjectAssignment::create([
            'fix_object_id' => $fo->id,
            'user_id'       => $employee->id,
            'assigned_from' => now()->subDay()->toDateString(),
        ]);

        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);

        Sanctum::actingAs($employee);
        $response = $this->postJson("/api/v1/schedules/{$schedule->id}/start", [
            'gps_start_lat' => 48.137154,
            'gps_start_lng' => 11.576124,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.status', 'started')
                 ->assertJsonPath('data.contract_hours_applied', '2.50');
    }

    public function test_workflow_transitions_in_correct_order(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fo       = FixObject::factory()->create();

        FixObjectAssignment::create([
            'fix_object_id' => $fo->id,
            'user_id'       => $employee->id,
            'assigned_from' => now()->subDay()->toDateString(),
        ]);

        $schedule  = FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);

        Sanctum::actingAs($employee);

        // Start
        $exec = $this->postJson("/api/v1/schedules/{$schedule->id}/start")->json('data');

        // photos_before
        $this->patchJson("/api/v1/executions/{$exec['id']}/status", ['status' => 'photos_before'])
             ->assertJsonPath('data.status', 'photos_before');

        // cleaning
        $this->patchJson("/api/v1/executions/{$exec['id']}/status", ['status' => 'cleaning'])
             ->assertJsonPath('data.status', 'cleaning');

        // photos_after
        $this->patchJson("/api/v1/executions/{$exec['id']}/status", ['status' => 'photos_after'])
             ->assertJsonPath('data.status', 'photos_after');

        // complete
        $this->postJson("/api/v1/executions/{$exec['id']}/complete", [
            'gps_end_lat' => 48.137154,
            'gps_end_lng' => 11.576124,
        ])->assertJsonPath('data.status', 'completed');

        // Schedule must be marked completed
        $this->assertEquals('completed', $schedule->fresh()->status->value);
    }

    public function test_invalid_workflow_transition_returns_error(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fo       = FixObject::factory()->create();

        FixObjectAssignment::create([
            'fix_object_id' => $fo->id,
            'user_id'       => $employee->id,
            'assigned_from' => now()->subDay()->toDateString(),
        ]);

        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);

        Sanctum::actingAs($employee);
        $exec = $this->postJson("/api/v1/schedules/{$schedule->id}/start")->json('data');

        // Jumping directly to 'cleaning' from 'started' — invalid
        $this->expectException(\LogicException::class);
        $execution = \App\Models\FixObjectExecution::find($exec['id']);
        $execution->transitionTo(ExecutionStatusEnum::Cleaning);
    }

    public function test_unassigned_employee_cannot_start_execution(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fo       = FixObject::factory()->create();
        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);

        Sanctum::actingAs($employee);
        $response = $this->postJson("/api/v1/schedules/{$schedule->id}/start");

        $response->assertStatus(500); // RuntimeException from service
    }

    public function test_financial_data_encrypted_in_database(): void
    {
        $fo = FixObject::factory()->withFinancials(1200.00, 700.00)->create();

        $raw = \Illuminate\Support\Facades\DB::table('fix_objects')
                ->where('id', $fo->id)
                ->first();

        $this->assertNotEquals('1200', $raw->price_per_month ?? '');
        $this->assertNotEquals('700',  $raw->internal_cost ?? '');

        // But decrypted values must be readable
        $fo->refresh();
        $this->assertEquals('1200', $fo->price_per_month);
        $this->assertEquals('700',  $fo->internal_cost);
    }
}
