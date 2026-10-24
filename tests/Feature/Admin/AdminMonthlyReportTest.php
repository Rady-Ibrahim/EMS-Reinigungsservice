<?php

namespace Tests\Feature\Admin;

use App\Enums\ExecutionStatusEnum;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObject;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Models\EmployeeShift;
use App\Models\MonthlyReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminMonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->administrator()->create());
    }

    private function seedExecutionData(User $employee): MonthlyReport
    {
        $fo       = FixObject::factory()->withContractHours(2.0)->create();
        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);

        FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $employee->id,
            'actual_start'           => now()->setYear(2026)->setMonth(10)->setDay(5)->setHour(8),
            'actual_end'             => now()->setYear(2026)->setMonth(10)->setDay(5)->setHour(9),
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 2.0,
        ]);

        $order = ExtraAuftrag::factory()->create();
        ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $employee->id,
            'work_start'       => now()->setYear(2026)->setMonth(10)->setDay(10)->setHour(9),
            'work_end'         => now()->setYear(2026)->setMonth(10)->setDay(10)->setHour(10),
            'work_minutes'     => 60,
            'paid_minutes'     => 60,
            'status'           => \App\Enums\ExtraExecutionStatusEnum::Completed,
        ]);

        return app(\App\Services\MonthlyReportEngineService::class)
            ->buildReport($employee, 2026, 10);
    }

    public function test_index_lists_monthly_reports_with_status(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->seedExecutionData($employee);

        $this->get(route('admin.monthly-reports.index', ['year' => 2026, 'month' => 10]))
             ->assertOk()
             ->assertSee($employee->name)
             ->assertSee('Entwurf');
    }

    public function test_index_shows_monthly_company_payout_total(): void
    {
        $company = Company::factory()->create();
        FixObject::factory()->create([
            'company_id' => $company->id,
            'company_payout' => '50.00',
            'valid_from' => '2026-10-01',
            'valid_until' => '2026-10-31',
        ]);
        ExtraAuftrag::factory()->create([
            'company_id' => $company->id,
            'company_payout' => '75.00',
            'scheduled_date' => '2026-10-22',
        ]);

        $this->get(route('admin.monthly-reports.index', ['year' => 2026, 'month' => 10]))
            ->assertOk()
            ->assertSee(__('monthly_index.company_payout_total'))
            ->assertSee('125,00 €');
    }

    public function test_show_displays_report_totals(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $report   = $this->seedExecutionData($employee);

        $this->get(route('admin.monthly-reports.show', $report))
             ->assertOk()
             ->assertSee('Fixobjekte')
             ->assertSee(__('messages.report.line_items'))
             ->assertSee('3,00'); // 2.0 fix + 1.0 extra
    }

    public function test_show_refreshes_draft_after_new_work_is_completed(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $report = app(\App\Services\MonthlyReportEngineService::class)
            ->buildReport($employee, 2026, 10);

        $fix = FixObject::factory()->withContractHours(2.0)->create(['title' => 'Freshly completed work']);
        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fix->id]);
        FixObjectExecution::create([
            'schedule_id' => $schedule->id,
            'user_id' => $employee->id,
            'actual_start' => now()->setYear(2026)->setMonth(10)->setDay(5)->setHour(8),
            'actual_end' => now()->setYear(2026)->setMonth(10)->setDay(5)->setHour(9),
            'status' => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 2.0,
        ]);

        $this->get(route('admin.monthly-reports.show', $report))
            ->assertOk()
            ->assertSee('Freshly completed work')
            ->assertSee('2,00');

        $this->assertSame(2.0, (float) $report->fresh()->total_paid_hours);
    }

    public function test_completed_shift_hours_and_approved_overtime_are_in_monthly_report(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $shift = EmployeeShift::factory()->create([
            'user_id' => $employee->id,
            'start_at' => '2026-10-12 08:00:00',
            'end_at' => '2026-10-12 16:00:00',
            'work_hours' => 7,
            'approved_extra_hours' => 1.5,
            'status' => \App\Enums\ShiftStatusEnum::Completed,
        ]);

        $report = app(\App\Services\MonthlyReportEngineService::class)
            ->buildReport($employee, 2026, 10);

        $this->assertSame(8.5, (float) $report->shift_paid_hours);
        $this->assertSame(8.5, (float) $report->total_paid_hours);
        $this->assertSame('employee_shift', app(\App\Services\MonthlyReportEngineService::class)
            ->lineItemsFor($employee, 2026, 10)->first()['job_type']);
    }

    public function test_admin_can_approve_and_freeze_report(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $report   = $this->seedExecutionData($employee);

        $this->post(route('admin.monthly-reports.approve', $report))
             ->assertRedirect()
             ->assertSessionHas('success');

        $this->assertNotNull($report->fresh()->approved_at);
        $this->assertNotNull($report->fresh()->snapshot);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => User::class,
            'auditable_id'   => $employee->id,
            'event'          => 'report_approved',
        ]);
    }

    public function test_approve_already_approved_report_fails(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $report   = $this->seedExecutionData($employee);
        app(\App\Services\MonthlyReportEngineService::class)
            ->approve($employee, 2026, 10, User::factory()->administrator()->create());

        $this->post(route('admin.monthly-reports.approve', $report))
             ->assertSessionHasErrors('error');
    }

    public function test_discrepancies_view_renders(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->seedExecutionData($employee);

        $this->get(route('admin.monthly-reports.discrepancies', ['year' => 2026, 'month' => 10]))
             ->assertOk()
             ->assertSee($employee->name);
    }

    public function test_export_excel_downloads_file(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->seedExecutionData($employee);

        $this->get(route('admin.monthly-reports.export-excel', ['year' => 2026, 'month' => 10]))
             ->assertOk()
             ->assertHeaderContains('content-disposition', 'attachment');
    }

    public function test_export_pdf_downloads_file(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $report   = $this->seedExecutionData($employee);

        $this->get(route('admin.monthly-reports.export-pdf', $report))
             ->assertOk()
             ->assertHeaderContains('content-type', 'pdf');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        // Forget the admin session set in setUp()
        Auth::logout();

        $this->get(route('admin.monthly-reports.index'))
             ->assertRedirect(route('login'));
    }
}