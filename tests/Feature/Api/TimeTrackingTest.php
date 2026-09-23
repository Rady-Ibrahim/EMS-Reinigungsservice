<?php

namespace Tests\Feature\Api;

use App\Enums\AdjustmentStatusEnum;
use App\Enums\AssigneeRoleEnum;
use App\Enums\ExecutionStatusEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragAssignee;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObject;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Models\TimeAdjustmentRequest;
use App\Models\TravelTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimeTrackingTest extends TestCase
{
    use RefreshDatabase;

    // ── GPS point recording ────────────────────────────────────────────────

    public function test_employee_can_record_gps_point_during_travel(): void
    {
        $user     = User::factory()->mitarbeiter()->create();
        $customer = \App\Models\Customer::factory()->create();
        $location = \App\Models\CustomerLocation::factory()->create(['customer_id' => $customer->id]);
        $admin    = User::factory()->administrator()->create();
        $order    = ExtraAuftrag::factory()->create([
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'created_by'  => $admin->id,
        ]);

        $track = TravelTrack::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $user->id,
            'departure_at'     => now(),
            'is_paid'          => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/travel-tracks/{$track->id}/gps", [
            'latitude'        => 48.137154,
            'longitude'       => 11.576124,
            'accuracy_meters' => 5.0,
            'recorded_at'     => now()->toIso8601String(),
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['data' => ['id', 'latitude', 'longitude', 'recorded_at']]);

        $this->assertDatabaseHas('gps_points', [
            'travel_track_id' => $track->id,
            'user_id'         => $user->id,
        ]);
    }

    public function test_gps_point_rejected_after_arrival(): void
    {
        $user     = User::factory()->mitarbeiter()->create();
        $customer = \App\Models\Customer::factory()->create();
        $location = \App\Models\CustomerLocation::factory()->create(['customer_id' => $customer->id]);
        $admin    = User::factory()->administrator()->create();
        $order    = ExtraAuftrag::factory()->create([
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'created_by'  => $admin->id,
        ]);

        $track = TravelTrack::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $user->id,
            'departure_at'     => now()->subHour(),
            'arrival_at'       => now(), // already arrived
            'travel_minutes'   => 60,
            'is_paid'          => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/travel-tracks/{$track->id}/gps", [
            'latitude'  => 48.137154,
            'longitude' => 11.576124,
        ])->assertStatus(422);
    }

    public function test_batch_gps_points_recorded(): void
    {
        $user     = User::factory()->mitarbeiter()->create();
        $customer = \App\Models\Customer::factory()->create();
        $location = \App\Models\CustomerLocation::factory()->create(['customer_id' => $customer->id]);
        $admin    = User::factory()->administrator()->create();
        $order    = ExtraAuftrag::factory()->create([
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'created_by'  => $admin->id,
        ]);

        $track = TravelTrack::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $user->id,
            'departure_at'     => now(),
            'is_paid'          => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/travel-tracks/{$track->id}/gps/batch", [
            'points' => [
                ['latitude' => 48.100, 'longitude' => 11.500, 'recorded_at' => now()->subMinutes(10)->toIso8601String()],
                ['latitude' => 48.110, 'longitude' => 11.520, 'recorded_at' => now()->subMinutes(7)->toIso8601String()],
                ['latitude' => 48.120, 'longitude' => 11.540, 'recorded_at' => now()->subMinutes(4)->toIso8601String()],
            ],
        ])->assertStatus(201)->assertJsonPath('points_created', 3);
    }

    // ── Monthly summary ───────────────────────────────────────────────────

    public function test_time_summary_returns_correct_structure(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/time-summary?year=2026&month=10');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'fix_paid_hours', 'extra_paid_hours', 'total_paid_hours',
                         'fix_actual_hours', 'extra_actual_hours',
                         'fix_count', 'extra_count', 'year', 'month',
                     ],
                 ]);
    }

    // ── Time Adjustment: submit ───────────────────────────────────────────

    public function test_employee_can_submit_time_adjustment_request(): void
    {
        $user     = User::factory()->mitarbeiter()->create();
        $fo       = FixObject::factory()->withContractHours(2.0)->create();
        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);

        FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $user->id,
            'actual_start'           => now()->subHours(3),
            'actual_end'             => now(),
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 2.0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/time-adjustments', [
            'job_type'        => 'fix_object',
            'job_id'          => $schedule->id,
            'requested_start' => now()->subHours(2)->toIso8601String(),
            'requested_end'   => now()->toIso8601String(),
            'reason'          => 'Habe früher angefangen als erfasst.',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.status', 'pending')
                 ->assertJsonStructure(['data' => ['id', 'original_start', 'original_end', 'requested_start', 'requested_end']]);

        // Original times must be frozen
        $adjustment = TimeAdjustmentRequest::first();
        $this->assertNotNull($adjustment->original_start);
        $this->assertNotNull($adjustment->original_end);
    }

    public function test_original_times_are_captured_from_execution(): void
    {
        $user     = User::factory()->mitarbeiter()->create();
        $fo       = FixObject::factory()->create();
        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);

        $originalStart = now()->subHours(5);
        $originalEnd   = now()->subHours(3);

        FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $user->id,
            'actual_start'           => $originalStart,
            'actual_end'             => $originalEnd,
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 2.0,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/time-adjustments', [
            'job_type'        => 'fix_object',
            'job_id'          => $schedule->id,
            'requested_start' => now()->subHours(4)->toIso8601String(),
            'requested_end'   => now()->subHours(2)->toIso8601String(),
            'reason'          => 'Beginn wurde nicht korrekt erfasst.',
        ]);

        $adjustment = TimeAdjustmentRequest::first();

        $this->assertEquals(
            $originalStart->toDateTimeString(),
            $adjustment->original_start->toDateTimeString(),
            'Original start must match execution actual_start'
        );
        $this->assertEquals(
            $originalEnd->toDateTimeString(),
            $adjustment->original_end->toDateTimeString(),
            'Original end must match execution actual_end'
        );
    }

    // ── Time Adjustment: admin approve ────────────────────────────────────

    public function test_admin_can_approve_fix_adjustment_without_changing_contract_hours(): void
    {
        $admin    = User::factory()->administrator()->create();
        $user     = User::factory()->mitarbeiter()->create();
        $fo       = FixObject::factory()->withContractHours(2.5)->create();
        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);

        $exec = FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $user->id,
            'actual_start'           => now()->subHours(4),
            'actual_end'             => now(),
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 2.5,
        ]);

        $adjustment = TimeAdjustmentRequest::create([
            'employee_id'     => $user->id,
            'job_type'        => 'fix_object',
            'job_id'          => $schedule->id,
            'requested_start' => now()->subHours(3),
            'requested_end'   => now()->subHour(),
            'original_start'  => now()->subHours(4),
            'original_end'    => now(),
            'reason'          => 'Test adjustment',
            'status'          => AdjustmentStatusEnum::Pending,
        ]);

        $service = app(\App\Services\TimeAdjustmentService::class);
        $service->approve($adjustment, $admin, 'Genehmigt nach Überprüfung');

        // Actual times updated
        $exec->refresh();
        $this->assertEquals(
            now()->subHours(3)->format('Y-m-d H:i'),
            $exec->actual_start->format('Y-m-d H:i')
        );

        // Contract hours MUST remain frozen
        $this->assertEquals('2.50', $exec->contract_hours_applied);

        // Adjustment status updated
        $this->assertEquals(AdjustmentStatusEnum::Approved, $adjustment->fresh()->status);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => FixObjectExecution::class,
            'auditable_id'   => $exec->id,
            'event'          => 'updated',
        ]);
    }

    public function test_admin_reject_does_not_change_execution_times(): void
    {
        $admin    = User::factory()->administrator()->create();
        $user     = User::factory()->mitarbeiter()->create();
        $fo       = FixObject::factory()->create();
        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);

        $originalStart = now()->subHours(4);
        $originalEnd   = now();

        FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $user->id,
            'actual_start'           => $originalStart,
            'actual_end'             => $originalEnd,
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 2.0,
        ]);

        $adjustment = TimeAdjustmentRequest::create([
            'employee_id'     => $user->id,
            'job_type'        => 'fix_object',
            'job_id'          => $schedule->id,
            'requested_start' => now()->subHours(2),
            'requested_end'   => now()->subHour(),
            'original_start'  => $originalStart,
            'original_end'    => $originalEnd,
            'reason'          => 'Test rejection',
            'status'          => AdjustmentStatusEnum::Pending,
        ]);

        $service = app(\App\Services\TimeAdjustmentService::class);
        $service->reject($adjustment, $admin, 'Nicht nachvollziehbar');

        $this->assertEquals(AdjustmentStatusEnum::Rejected, $adjustment->fresh()->status);
        $this->assertEquals('Nicht nachvollziehbar', $adjustment->fresh()->admin_note);

        // Execution times untouched
        $exec = FixObjectExecution::where('schedule_id', $schedule->id)->first();
        $this->assertEquals(
            $originalStart->format('Y-m-d H:i'),
            $exec->actual_start->format('Y-m-d H:i')
        );
    }
}
