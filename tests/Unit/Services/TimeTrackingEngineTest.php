<?php

namespace Tests\Unit\Services;

use App\Enums\ExecutionStatusEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Models\TravelTrack;
use App\Models\User;
use App\Services\TimeTrackingEngine;
use App\ValueObjects\TimeTrackingSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeTrackingEngineTest extends TestCase
{
    use RefreshDatabase;

    private TimeTrackingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(TimeTrackingEngine::class);
    }

    // ── Fix execution: paid = contract hours always ───────────────────────

    public function test_fix_execution_paid_hours_equal_contract_hours(): void
    {
        $fo       = \App\Models\FixObject::factory()->withContractHours(3.0)->create();
        $schedule = \App\Models\FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);
        $user     = User::factory()->mitarbeiter()->create();

        $execution = FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $user->id,
            'actual_start'           => now()->subHours(4), // 4 hours actual
            'actual_end'             => now(),
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 3.0,  // only 3 are paid
        ]);

        $summary = $this->engine->summarizeFixExecution($execution);

        $this->assertInstanceOf(TimeTrackingSummary::class, $summary);
        $this->assertEquals('fix_object', $summary->jobType);
        $this->assertEquals(3.0, $summary->paidHours);    // contract hours
        $this->assertEquals(4.0, $summary->actualHours);  // actual time (for audit)
        $this->assertEquals(0.0, $summary->travelHours);
        $this->assertFalse($summary->hasTravelTime);
    }

    public function test_fix_execution_deviation_is_correct(): void
    {
        $fo       = \App\Models\FixObject::factory()->withContractHours(2.0)->create();
        $schedule = \App\Models\FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);
        $user     = User::factory()->mitarbeiter()->create();

        $execution = FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $user->id,
            'actual_start'           => now()->subHours(3), // worked 3h
            'actual_end'             => now(),
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 2.0, // paid only 2h
        ]);

        $summary = $this->engine->summarizeFixExecution($execution);

        // Deviation: worked 1h more than paid
        $this->assertEquals(1.0, $summary->deviationHours());
    }

    public function test_fix_execution_contract_hours_applied_is_never_recalculated(): void
    {
        $fo       = \App\Models\FixObject::factory()->withContractHours(2.5)->create();
        $schedule = \App\Models\FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);
        $user     = User::factory()->mitarbeiter()->create();

        $execution = FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $user->id,
            'actual_start'           => now()->subHours(1),
            'actual_end'             => now(),
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 2.5, // frozen
        ]);

        // Even if contract changes
        $fo->update(['contract_hours' => 5.0]);

        $summary = $this->engine->summarizeFixExecution($execution->fresh());

        // Must still use the frozen 2.5 — not the updated 5.0
        $this->assertEquals(2.5, $summary->paidHours);
        $this->assertEquals(2.5, $summary->contractHours);
    }

    // ── Extra execution: paid = work + travel (if paid) ──────────────────

    public function test_extra_execution_with_unpaid_travel(): void
    {
        $customer = \App\Models\Customer::factory()->create();
        $location = \App\Models\CustomerLocation::factory()->create(['customer_id' => $customer->id]);
        $admin    = User::factory()->administrator()->create();
        $user     = User::factory()->mitarbeiter()->create();

        $order = ExtraAuftrag::factory()->create([
            'customer_id'         => $customer->id,
            'location_id'         => $location->id,
            'created_by'          => $admin->id,
            'is_travel_time_paid' => false,
        ]);

        // 45 minutes travel (unpaid)
        TravelTrack::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $user->id,
            'departure_at'     => now()->subMinutes(105),
            'arrival_at'       => now()->subMinutes(60),
            'travel_minutes'   => 45,
            'is_paid'          => false,
        ]);

        // 60 minutes work
        $execution = ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $user->id,
            'work_start'       => now()->subHour(),
            'work_end'         => now(),
            'work_minutes'     => 60,
            'paid_minutes'     => 60, // travel not paid
            'status'           => ExtraExecutionStatusEnum::Completed,
        ]);

        $summary = $this->engine->summarizeExtraExecution($execution);

        $this->assertEquals(1.0, $summary->actualHours);  // 60 min work
        $this->assertEquals(1.0, $summary->paidHours);    // no travel paid
        $this->assertEquals(0.0, $summary->travelHours);  // travel not paid
        $this->assertTrue($summary->hasTravelTime);        // travel exists
        $this->assertFalse($summary->travelIsPaid);
    }

    public function test_extra_execution_with_paid_travel(): void
    {
        $customer = \App\Models\Customer::factory()->create();
        $location = \App\Models\CustomerLocation::factory()->create(['customer_id' => $customer->id]);
        $admin    = User::factory()->administrator()->create();
        $user     = User::factory()->mitarbeiter()->create();

        $order = ExtraAuftrag::factory()->create([
            'customer_id'         => $customer->id,
            'location_id'         => $location->id,
            'created_by'          => $admin->id,
            'is_travel_time_paid' => true,
        ]);

        // 30 minutes travel (paid)
        TravelTrack::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $user->id,
            'departure_at'     => now()->subMinutes(90),
            'arrival_at'       => now()->subMinutes(60),
            'travel_minutes'   => 30,
            'is_paid'          => true,  // frozen at arrival
        ]);

        // 60 minutes work
        $execution = ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $user->id,
            'work_start'       => now()->subHour(),
            'work_end'         => now(),
            'work_minutes'     => 60,
            'paid_minutes'     => 90, // 60 work + 30 travel
            'status'           => ExtraExecutionStatusEnum::Completed,
        ]);

        $summary = $this->engine->summarizeExtraExecution($execution);

        $this->assertEquals(1.0,  $summary->actualHours); // 60 min
        $this->assertEquals(1.5,  $summary->paidHours);   // 60+30 = 90 min
        $this->assertEquals(0.5,  $summary->travelHours); // 30 min
        $this->assertTrue($summary->travelIsPaid);
    }

    // ── Monthly totals ────────────────────────────────────────────────────

    public function test_monthly_totals_aggregates_both_job_types(): void
    {
        $user     = User::factory()->mitarbeiter()->create();
        $fo       = \App\Models\FixObject::factory()->withContractHours(2.0)->create();
        $schedule = \App\Models\FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);

        // One Fix execution (2h paid regardless of actual)
        FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $user->id,
            'actual_start'           => now()->setYear(2026)->setMonth(10)->setDay(5)->setHour(8),
            'actual_end'             => now()->setYear(2026)->setMonth(10)->setDay(5)->setHour(9),
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 2.0,
        ]);

        // One Extra execution (1.5h paid)
        $customer  = \App\Models\Customer::factory()->create();
        $location  = \App\Models\CustomerLocation::factory()->create(['customer_id' => $customer->id]);
        $admin     = User::factory()->administrator()->create();
        $order     = ExtraAuftrag::factory()->create([
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'created_by'  => $admin->id,
        ]);

        ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $user->id,
            'work_start'       => now()->setYear(2026)->setMonth(10)->setDay(10)->setHour(9),
            'work_end'         => now()->setYear(2026)->setMonth(10)->setDay(10)->setHour(10)->setMinute(30),
            'work_minutes'     => 90,
            'paid_minutes'     => 90,
            'status'           => ExtraExecutionStatusEnum::Completed,
        ]);

        $totals = $this->engine->monthlyTotalsForEmployee($user, 2026, 10);

        $this->assertEquals(2.0,  $totals['fix_paid_hours']);
        $this->assertEquals(1.5,  $totals['extra_paid_hours']);
        $this->assertEquals(3.5,  $totals['total_paid_hours']);
        $this->assertEquals(1,    $totals['fix_count']);
        $this->assertEquals(1,    $totals['extra_count']);
    }
}
