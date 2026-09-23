<?php

namespace Tests\Unit\Services;

use App\Enums\AuditEventEnum;
use App\Enums\ExecutionStatusEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObject;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Models\MonthlyReport;
use App\Models\TravelTrack;
use App\Models\User;
use App\Services\MonthlyReportEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyReportEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private MonthlyReportEngineService $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(MonthlyReportEngineService::class);
    }

    private function makeFixExecution(User $user, float $contractHours, int $year = 2026, int $month = 10): FixObjectExecution
    {
        $fo       = FixObject::factory()->withContractHours($contractHours)->create();
        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);

        return FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $user->id,
            'actual_start'           => now()->setYear($year)->setMonth($month)->setDay(5)->setHour(8),
            'actual_end'             => now()->setYear($year)->setMonth($month)->setDay(5)->setHour(11),
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => $contractHours,
        ]);
    }

    private function makeExtraExecution(
        User $user,
        int $workMinutes,
        int $year = 2026,
        int $month = 10,
        bool $travelPaid = false,
        int $travelMinutes = 0,
    ): ExtraAuftragExecution {
        $customer = Customer::factory()->create();
        $location = CustomerLocation::factory()->create(['customer_id' => $customer->id]);
        $order    = ExtraAuftrag::factory()->create([
            'customer_id'         => $customer->id,
            'location_id'         => $location->id,
            'is_travel_time_paid' => $travelPaid,
        ]);

        if ($travelMinutes > 0) {
            TravelTrack::create([
                'extra_auftrag_id' => $order->id,
                'user_id'          => $user->id,
                'departure_at'     => now()->setYear($year)->setMonth($month)->setDay(10)->setHour(7),
                'arrival_at'       => now()->setYear($year)->setMonth($month)->setDay(10)->setHour(7)->addMinutes($travelMinutes),
                'travel_minutes'   => $travelMinutes,
                'is_paid'          => $travelPaid,
            ]);
        }

        $end = now()->setYear($year)->setMonth($month)->setDay(10)->setHour(9)->addMinutes($workMinutes);

        return ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $user->id,
            'work_start'       => now()->setYear($year)->setMonth($month)->setDay(10)->setHour(9),
            'work_end'         => $end,
            'work_minutes'     => $workMinutes,
            'paid_minutes'     => $workMinutes + ($travelPaid ? $travelMinutes : 0),
            'status'           => ExtraExecutionStatusEnum::Completed,
        ]);
    }

    // ── buildReport ───────────────────────────────────────────────────────

    public function test_build_report_aggregates_fix_and_extra_hours(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $this->makeFixExecution($user, 2.0);
        $this->makeExtraExecution($user, 60, travelPaid: false);

        $report = $this->engine->buildReport($user, 2026, 10);

        $this->assertInstanceOf(MonthlyReport::class, $report);
        $this->assertEquals(2.0,  (float) $report->fix_paid_hours);
        $this->assertEquals(1.0,  (float) $report->extra_work_hours);
        $this->assertEquals(3.0,  (float) $report->total_paid_hours);
        $this->assertEquals(0.0,  (float) $report->extra_travel_hours);
        $this->assertNull($report->approved_at);
    }

    public function test_build_report_travel_only_included_when_paid(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $this->makeExtraExecution($user, 60, travelPaid: true, travelMinutes: 30);

        $report = $this->engine->buildReport($user, 2026, 10);

        $this->assertEquals(1.0, (float) $report->extra_work_hours);
        $this->assertEquals(0.5, (float) $report->extra_travel_hours);
        $this->assertEquals(1.5, (float) $report->extra_paid_hours);
    }

    public function test_build_report_unpaid_travel_not_charged(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $this->makeExtraExecution($user, 60, travelPaid: false, travelMinutes: 30);

        $report = $this->engine->buildReport($user, 2026, 10);

        $this->assertEquals(1.0, (float) $report->extra_work_hours);
        $this->assertEquals(0.0, (float) $report->extra_travel_hours);
        $this->assertEquals(1.0, (float) $report->extra_paid_hours);
    }

    public function test_build_report_is_regenerated_until_approval(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $this->makeFixExecution($user, 2.0);

        $report = $this->engine->buildReport($user, 2026, 10);
        $this->assertEquals(2.0, (float) $report->total_paid_hours);

        // New work lands after a report was built but before approval
        $this->makeExtraExecution($user, 60);

        $reportFresh = $this->engine->buildReport($user, 2026, 10);
        $this->assertEquals(3.0, (float) $reportFresh->total_paid_hours);
    }

    // ── approve / freeze ──────────────────────────────────────────────────

    public function test_approve_freezes_report_and_writes_snapshot(): void
    {
        $admin = User::factory()->administrator()->create();
        $user  = User::factory()->mitarbeiter()->create();
        $this->makeFixExecution($user, 2.0);

        $report = $this->engine->approve($user, 2026, 10, $admin);

        $this->assertTrue($report->isApproved());
        $this->assertEquals($admin->id, $report->approved_by);
        $this->assertNotNull($report->snapshot);
        $this->assertEquals(2.0, (float) $report->snapshot['aggregate']['total_paid_hours']);
        $this->assertCount(1, $report->snapshot['line_items']);
    }

    public function test_approving_twice_throws(): void
    {
        $this->expectException(\RuntimeException::class);

        $admin = User::factory()->administrator()->create();
        $user  = User::factory()->mitarbeiter()->create();
        $this->makeFixExecution($user, 2.0);

        $this->engine->approve($user, 2026, 10, $admin);
        $this->engine->approve($user, 2026, 10, $admin);
    }

    public function test_approved_report_not_rebuilt_after_data_change(): void
    {
        $admin = User::factory()->administrator()->create();
        $user  = User::factory()->mitarbeiter()->create();
        $this->makeFixExecution($user, 2.0);

        $report = $this->engine->approve($user, 2026, 10, $admin);
        $this->assertTrue($report->isApproved());

        // Late extra execution arrives — approved report must NOT change
        $this->makeExtraExecution($user, 60);

        $frozen = $this->engine->buildReport($user, 2026, 10);
        $this->assertEquals(2.0, (float) $frozen->total_paid_hours);
        $this->assertNotNull($frozen->approved_at);
    }

    public function test_approve_writes_report_approved_audit_log(): void
    {
        $admin = User::factory()->administrator()->create();
        $user  = User::factory()->mitarbeiter()->create();
        $this->makeFixExecution($user, 2.0);

        $this->engine->approve($user, 2026, 10, $admin);

        $log = AuditLog::where('event', AuditEventEnum::ReportApproved->value)
                       ->where('auditable_id', $user->id)
                       ->where('auditable_type', User::class)
                       ->first();

        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->user_id);
        $this->assertEquals('2026-10', $log->new_values['period']);
    }

    // ── lineItemsFor ──────────────────────────────────────────────────────

    public function test_line_items_contain_both_job_types_with_labels(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $fix  = $this->makeFixExecution($user, 2.0);
        $extra = $this->makeExtraExecution($user, 60);

        $items = $this->engine->lineItemsFor($user, 2026, 10);

        $this->assertCount(2, $items);

        $fixItem = $items->firstWhere('job_type', 'fix_object');
        $this->assertNotNull($fixItem);
        $this->assertEquals(2.0, $fixItem['paid_hours']);
        $this->assertNotEmpty($fixItem['label']);

        $extraItem = $items->firstWhere('job_type', 'extra_auftrag');
        $this->assertNotNull($extraItem);
        $this->assertEquals(1.0, $extraItem['paid_hours']);
        $this->assertNotEquals($fix->id, $extraItem['job_id'] ?? 0);
    }

    // ── discrepanciesForMonth ─────────────────────────────────────────────

    public function test_discrepancies_report_paid_and_actual_balance(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        // Paid 2h (contract), actually worked 3h → deviation +1h (worked 1h more)
        $this->makeFixExecution($user, 2.0);

        $rows = $this->engine->discrepanciesForMonth(2026, 10);

        $this->assertNotEmpty($rows);
        $row = $rows->firstWhere('employee_id', $user->id);
        $this->assertNotNull($row);
        $this->assertEquals(2.0, $row['paid_hours']);
        $this->assertEquals(3.0, $row['actual_hours']);
        $this->assertEquals(2.0, $row['balance']);
    }

    public function test_discrepancies_plans_from_active_assignment(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $fo   = FixObject::factory()->withContractHours(2.0)->create();

        \App\Models\FixObjectAssignment::create([
            'fix_object_id'   => $fo->id,
            'user_id'         => $user->id,
            'assigned_from'   => now()->startOfMonth()->toDateString(),
            'assigned_until'  => now()->endOfMonth()->toDateString(),
        ]);

        $rows = $this->engine->discrepanciesForMonth(now()->year, now()->month);

        $row = $rows->firstWhere('employee_id', $user->id);
        $this->assertNotNull($row);
        $this->assertGreaterThan(0, $row['planned_hours']);
    }
}