<?php

namespace Tests\Feature\Admin;

use App\Enums\ExtraExecutionStatusEnum;
use App\Enums\ExecutionStatusEnum;
use App\Models\EmployeeProfile;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObject;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Models\MonthlyReport;
use App\Models\User;
use App\Services\AuthService;
use App\Services\MonthlyReportEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyReportProfitabilityTest extends TestCase
{
    use RefreshDatabase;

    private function engine(): MonthlyReportEngineService
    {
        return app(MonthlyReportEngineService::class);
    }

    /**
     * Set the employer hourly cost on the user's profile.
     *
     * UserObserver already provisions a profile for Mitarbeiter/Vorarbeiter, so
     * this updates the existing one; administrators get no profile at all, so
     * one is created for them here.
     */
    private function setHourlyCost(User $user, float $cost): EmployeeProfile
    {
        $profile = EmployeeProfile::where('user_id', $user->id)->first();

        if ($profile === null) {
            return EmployeeProfile::factory()->withHourlyCost($cost)->create(['user_id' => $user->id]);
        }

        $profile->update(['hourly_cost' => $cost]);

        return $profile->fresh();
    }

    /**
     * One completed FixObject visit on 2026-10-05 for $employee, billing
     * $paidHours of contract time.
     */
    private function fixVisit(
        User $employee,
        FixObject $fix,
        float $paidHours = 2.0,
        int $day = 5,
        int $year = 2026,
        int $month = 10
    ): void {
        $visitDate = now()->setYear($year)->setMonth($month)->setDay($day);

        $schedule = FixObjectSchedule::factory()->create([
            'fix_object_id' => $fix->id,
            'scheduled_date' => $visitDate->toDateString(),
        ]);

        FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $employee->id,
            'actual_start'           => $visitDate->copy()->setHour(8),
            'actual_end'             => $visitDate->copy()->setHour(9),
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => $paidHours,
        ]);
    }

    private function extraJob(
        User $employee,
        ExtraAuftrag $order,
        int $paidMinutes = 60,
        int $day = 10,
        int $year = 2026,
        int $month = 10
    ): void {
        ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $employee->id,
            'work_start'       => now()->setYear($year)->setMonth($month)->setDay($day)->setHour(9),
            'work_end'         => now()->setYear($year)->setMonth($month)->setDay($day)->setHour(10),
            'work_minutes'     => $paidMinutes,
            'paid_minutes'     => $paidMinutes,
            'status'           => ExtraExecutionStatusEnum::Completed,
        ]);
    }

    // ── Cost ───────────────────────────────────────────────────────────────

    public function test_employee_cost_is_paid_hours_times_hourly_cost(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->setHourlyCost($employee, 15.00);

        $this->fixVisit($employee, FixObject::factory()->withContractHours(2.0)->create(), 2.0);
        $this->extraJob($employee, ExtraAuftrag::factory()->create(), 60); // +1h

        // 3 paid hours x 15.00
        $profitability = $this->engine()->profitabilityFor($employee, 2026, 10);

        $this->assertSame(3.0, (float) $this->engine()->aggregateFor($employee, 2026, 10)['total_paid_hours']);
        $this->assertSame(45.0, $profitability['employee_cost']);
    }

    public function test_hourly_cost_is_encrypted_at_rest_and_still_readable(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $profile = $this->setHourlyCost($employee, 17.25);

        $stored = \DB::table('employee_profiles')->where('user_id', $employee->id)->value('hourly_cost');

        $this->assertNotSame('17.25', $stored, 'hourly_cost must not be stored in plaintext');
        $this->assertSame(17.25, $this->engine()->hourlyCostFor($employee));
    }

    public function test_missing_hourly_cost_counts_as_zero_not_as_free_revenue_profit(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->fixVisit($employee, FixObject::factory()->withFinancials(400.0)->create(), 2.0);

        $profitability = $this->engine()->profitabilityFor($employee, 2026, 10);

        $this->assertSame(0.0, $profitability['employee_cost']);
        $this->assertSame(400.0, $profitability['revenue']);
        $this->assertSame(400.0, $profitability['profit']);
    }

    // ── Revenue basis ──────────────────────────────────────────────────────

    public function test_fix_revenue_uses_the_monthly_price(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fix = FixObject::factory()->create([
            'price_per_month' => 500.00,
            'price_per_hour'  => 40.00,
        ]);

        $this->fixVisit($employee, $fix, 2.0);

        // Monthly price wins even though an hourly price is also set.
        $this->assertSame(500.0, $this->engine()->profitabilityFor($employee, 2026, 10)['revenue']);
    }

    public function test_fix_revenue_falls_back_to_hourly_price_times_paid_hours(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fix = FixObject::factory()->create([
            'price_per_month' => null,
            'price_per_hour'  => 30.00,
        ]);

        $this->fixVisit($employee, $fix, 2.0, day: 5);
        $this->fixVisit($employee, $fix, 3.0, day: 12);

        // 30.00 x (2 + 3) paid hours
        $this->assertSame(150.0, $this->engine()->profitabilityFor($employee, 2026, 10)['revenue']);
    }

    public function test_revenue_only_counts_the_requested_month(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fix = FixObject::factory()->create(['price_per_month' => 500.00, 'price_per_hour' => null]);

        $this->fixVisit($employee, $fix, 2.0, day: 5, month: 10);
        $this->fixVisit($employee, $fix, 2.0, day: 5, month: 11);

        $this->assertSame(500.0, $this->engine()->profitabilityFor($employee, 2026, 10)['revenue']);
        $this->assertSame(500.0, $this->engine()->profitabilityFor($employee, 2026, 11)['revenue']);
    }

    public function test_finished_visit_is_excluded_from_revenue(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fix = FixObject::factory()->create(['price_per_month' => 500.00, 'price_per_hour' => null]);

        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fix->id]);
        FixObjectExecution::create([ // no actual_end → not performed
            'schedule_id'            => $schedule->id,
            'user_id'                => $employee->id,
            'actual_start'           => now()->setYear(2026)->setMonth(10)->setDay(5)->setHour(8),
            'actual_end'             => null,
            'status'                 => ExecutionStatusEnum::Started,
            'contract_hours_applied' => 2.0,
        ]);

        $this->assertSame(0.0, $this->engine()->profitabilityFor($employee, 2026, 10)['revenue']);
    }

    public function test_monthly_report_refresh_includes_completed_employee_work(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $date = now();
        $this->fixVisit(
            $employee,
            FixObject::factory()->withContractHours(2.0)->create(),
            2.0,
            day: $date->day,
            year: $date->year,
            month: $date->month
        );

        $report = $this->engine()->employeesWithReports($date->year, $date->month)
            ->firstWhere('user_id', $employee->id);

        $this->assertNotNull($report);
        $this->assertSame(2.0, (float) $report->total_paid_hours);
        $this->assertCount(1, $this->engine()->lineItemsFor($employee, $date->year, $date->month));
    }

    // ── Even split between employees ───────────────────────────────────────

    public function test_monthly_fee_is_split_evenly_between_two_employees(): void
    {
        $first  = User::factory()->mitarbeiter()->create();
        $second = User::factory()->mitarbeiter()->create();

        $fix = FixObject::factory()->create(['price_per_month' => 500.00, 'price_per_hour' => null]);

        $this->fixVisit($first,  $fix, 2.0, day: 5);
        $this->fixVisit($second, $fix, 2.0, day: 12);

        $engine = $this->engine();

        $this->assertSame(250.0, $engine->profitabilityFor($first, 2026, 10)['revenue']);
        $this->assertSame(250.0, $engine->profitabilityFor($second, 2026, 10)['revenue']);
    }

    public function test_repeated_visits_by_one_employee_still_bill_the_fee_once(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fix = FixObject::factory()->create(['price_per_month' => 500.00, 'price_per_hour' => null]);

        $this->fixVisit($employee, $fix, 2.0, day: 5);
        $this->fixVisit($employee, $fix, 2.0, day: 12);
        $this->fixVisit($employee, $fix, 2.0, day: 19);

        // Six hours of work, but one monthly fee.
        $this->assertSame(6.0, (float) $this->engine()->aggregateFor($employee, 2026, 10)['total_paid_hours']);
        $this->assertSame(500.0, $this->engine()->profitabilityFor($employee, 2026, 10)['revenue']);
    }

    public function test_extra_order_price_is_split_evenly_between_two_employees(): void
    {
        $first  = User::factory()->mitarbeiter()->create();
        $second = User::factory()->mitarbeiter()->create();

        $order = ExtraAuftrag::factory()->create(['price' => 240.00]);

        $this->extraJob($first,  $order, 60, day: 10);
        $this->extraJob($second, $order, 60, day: 11);

        $engine = $this->engine();

        $this->assertSame(120.0, $engine->profitabilityFor($first, 2026, 10)['revenue']);
        $this->assertSame(120.0, $engine->profitabilityFor($second, 2026, 10)['revenue']);
    }

    public function test_per_employee_revenue_adds_up_to_the_company_revenue(): void
    {
        $first  = User::factory()->mitarbeiter()->create();
        $second = User::factory()->mitarbeiter()->create();
        $third  = User::factory()->mitarbeiter()->create();

        $sharedFix = FixObject::factory()->create(['price_per_month' => 500.00, 'price_per_hour' => null]);
        $soloFix   = FixObject::factory()->create(['price_per_month' => 300.00, 'price_per_hour' => null]);
        $hourlyFix = FixObject::factory()->create(['price_per_month' => null, 'price_per_hour' => 25.00]);

        $this->fixVisit($first,  $sharedFix, 2.0, day: 5);
        $this->fixVisit($second, $sharedFix, 2.0, day: 6);
        $this->fixVisit($first,  $soloFix,   2.0, day: 7);
        $this->fixVisit($second, $hourlyFix, 4.0, day: 8);
        $this->fixVisit($third,  $hourlyFix, 2.0, day: 9);

        $order = ExtraAuftrag::factory()->create(['price' => 180.00]);
        $this->extraJob($first, $order, 60, day: 10);
        $this->extraJob($third, $order, 60, day: 11);

        // Company revenue: 500 (monthly) + 300 (monthly) + (4+2)x25 (hourly) + 180 (extra) = 1130
        $map = $this->engine()->monthlyRevenueMap(2026, 10);

        $this->assertEqualsWithDelta(1130.00, array_sum($map), 0.001, 'splitting must not create or lose money');
        $this->assertEqualsWithDelta(250.0 + 300.0 + 90.0, $map[$first->id],  0.001);
        $this->assertEqualsWithDelta(250.0 + 75.0,              $map[$second->id], 0.001);
        $this->assertEqualsWithDelta(75.0 + 90.0,               $map[$third->id],  0.001);
    }

    public function test_employee_working_a_second_object_keeps_both_revenues(): void
    {
        $employee = User::factory()->mitarbeiter()->create();

        $a = FixObject::factory()->create(['price_per_month' => 400.00, 'price_per_hour' => null]);
        $b = FixObject::factory()->create(['price_per_month' => 250.00, 'price_per_hour' => null]);

        $this->fixVisit($employee, $a, 2.0, day: 5);
        $this->fixVisit($employee, $b, 2.0, day: 6);

        $this->assertSame(650.0, $this->engine()->profitabilityFor($employee, 2026, 10)['revenue']);
    }

    // ── Profit ─────────────────────────────────────────────────────────────

    public function test_profit_is_revenue_minus_employee_cost(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->setHourlyCost($employee, 12.00);

        $this->fixVisit($employee, FixObject::factory()->create([
            'price_per_month' => 500.00,
            'price_per_hour'  => null,
        ]), 10.0);

        $profitability = $this->engine()->profitabilityFor($employee, 2026, 10);

        $this->assertSame(500.0, $profitability['revenue']);
        $this->assertSame(120.0, $profitability['employee_cost']);  // 10h x 12.00
        $this->assertSame(380.0, $profitability['profit']);
    }

    public function test_a_loss_month_reports_negative_profit(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->setHourlyCost($employee, 30.00);

        $this->fixVisit($employee, FixObject::factory()->create([
            'price_per_month' => 100.00,
            'price_per_hour'  => null,
        ]), 10.0);

        $this->assertSame(-200.0, $this->engine()->profitabilityFor($employee, 2026, 10)['profit']);
    }

    // ── Persistence and approval freeze ────────────────────────────────────

    public function test_build_report_persists_the_financial_figures(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->setHourlyCost($employee, 10.00);

        $this->fixVisit($employee, FixObject::factory()->create([
            'price_per_month' => 500.00,
            'price_per_hour'  => null,
        ]), 20.0);

        $report = $this->engine()->buildReport($employee, 2026, 10);

        $this->assertSame(500.00, (float) $report->revenue);
        $this->assertSame(200.00, (float) $report->employee_cost);
        $this->assertSame(300.00, (float) $report->profit);
        $this->assertSame(10.00, (float) $report->hourly_cost_applied);
        $this->assertSame(60.0, $report->profitMarginPercent());
    }

    public function test_profit_margin_is_null_without_revenue(): void
    {
        $report = new MonthlyReport(['revenue' => 0, 'profit' => 0]);

        $this->assertNull($report->profitMarginPercent());
    }

    public function test_approval_freezes_profitability_in_the_snapshot(): void
    {
        $admin    = User::factory()->administrator()->create();
        $employee = User::factory()->mitarbeiter()->create();
        $this->setHourlyCost($employee, 10.00);

        $this->fixVisit($employee, FixObject::factory()->create([
            'price_per_month' => 500.00,
            'price_per_hour'  => null,
        ]), 20.0);

        $report = $this->engine()->approve($employee, 2026, 10, $admin);

        $this->assertSame(300.0, (float) $report->snapshot['profitability']['profit']);
        $this->assertSame(500.0, (float) $report->snapshot['profitability']['revenue']);
    }

    public function test_an_approved_report_is_not_restated_when_a_rate_changes(): void
    {
        $admin    = User::factory()->administrator()->create();
        $employee = User::factory()->mitarbeiter()->create();
        $profile  = $this->setHourlyCost($employee, 10.00);

        $this->fixVisit($employee, FixObject::factory()->create([
            'price_per_month' => 500.00,
            'price_per_hour'  => null,
        ]), 20.0);

        $this->engine()->approve($employee, 2026, 10, $admin);

        $profile->update(['hourly_cost' => 25.00]);
        $fix = FixObject::first();
        $fix->update(['price_per_month' => 900.00]);

        $report = $this->engine()->buildReport($employee, 2026, 10);

        $this->assertSame(500.00, (float) $report->revenue);
        $this->assertSame(300.00, (float) $report->profit);
    }

    // ── API exposure ───────────────────────────────────────────────────────

    private function actingWithToken(User $user): self
    {
        return $this->withToken(app(AuthService::class)->createApiToken($user)['token']);
    }

    public function test_api_hides_financials_from_employees(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->setHourlyCost($employee, 20.00);

        $this->fixVisit($employee, FixObject::factory()->create([
            'price_per_month' => 500.00,
            'price_per_hour'  => null,
        ]), 5.0);

        $response = $this->actingWithToken($employee)
            ->getJson('/api/v1/monthly-reports?year=2026&month=10')
            ->assertOk();

        $response->assertJsonPath('data.total_paid_hours', 5);

        foreach (['hourly_cost', 'revenue', 'employee_cost', 'profit', 'profit_margin_percent'] as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $response->json('data'),
                "{$key} must not be sent to a non-admin employee."
            );
        }
    }

    public function test_api_hides_financials_from_vorarbeiter(): void
    {
        $supervisor = User::factory()->vorarbeiter()->create();
        $this->setHourlyCost($supervisor, 20.00);

        $this->fixVisit($supervisor, FixObject::factory()->create([
            'price_per_month' => 500.00,
            'price_per_hour'  => null,
        ]), 5.0);

        $response = $this->actingWithToken($supervisor)
            ->getJson('/api/v1/monthly-reports?year=2026&month=10')
            ->assertOk();

        $this->assertArrayNotHasKey('profit', $response->json('data'));
    }

    public function test_api_includes_financials_for_administrator(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->setHourlyCost($admin, 17.80);

        $this->fixVisit($admin, FixObject::factory()->create([
            'price_per_month' => 500.00,
            'price_per_hour'  => null,
        ]), 5.0);

        // 5h x 17.80 = 89.00 cost, 500.00 revenue, 411.00 profit, 82.2 % margin
        $this->actingWithToken($admin)
            ->getJson('/api/v1/monthly-reports?year=2026&month=10')
            ->assertOk()
            ->assertJsonPath('data.revenue', 500)
            ->assertJsonPath('data.hourly_cost', 17.8)
            ->assertJsonPath('data.employee_cost', 89)
            ->assertJsonPath('data.profit', 411)
            ->assertJsonPath('data.profit_margin_percent', 82.2);
    }

    // ── Admin web view ─────────────────────────────────────────────────────

    public function test_admin_report_page_shows_the_profitability_figures(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->setHourlyCost($admin, 10.00);

        $this->fixVisit($admin, FixObject::factory()->create([
            'price_per_month' => 500.00,
            'price_per_hour'  => null,
        ]), 20.0);

        $report = $this->engine()->buildReport($admin, 2026, 10);

        $this->actingAs($admin)
            ->get(route('admin.monthly-reports.show', $report))
            ->assertOk()
            ->assertSee(__('messages.report.profitability'))
            ->assertSee(__('messages.report.revenue'))
            ->assertSee(__('messages.report.profit'))
            // 500,00 revenue · 200,00 cost · 300,00 profit
            ->assertSee('500,00')
            ->assertSee('200,00')
            ->assertSee('300,00');
    }

    public function test_profitability_page_is_reachable_only_by_administrator(): void
    {
        $supervisor = User::factory()->vorarbeiter()->create();

        $report = MonthlyReport::create([
            'user_id' => $supervisor->id,
            'year'    => 2026,
            'month'   => 10,
        ]);

        $this->actingAs($supervisor)
            ->get(route('admin.monthly-reports.show', $report))
            ->assertForbidden();
    }
}
