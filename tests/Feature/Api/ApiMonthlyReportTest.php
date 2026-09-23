<?php

namespace Tests\Feature\Api;

use App\Enums\ExecutionStatusEnum;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObject;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Models\MonthlyReport;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiMonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private function actingWithToken(User $user): self
    {
        $token = app(AuthService::class)->createApiToken($user)['token'];

        return $this->withToken($token);
    }

    private function seedExecutionData(User $employee): void
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
    }

    public function test_employee_sees_own_monthly_report(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->seedExecutionData($employee);

        $this->actingWithToken($employee)
             ->getJson('/api/v1/monthly-reports?year=2026&month=10')
             ->assertOk()
             ->assertJsonPath('data.user_id', $employee->id)
             ->assertJsonPath('data.total_paid_hours', 3)
             ->assertJsonPath('data.is_approved', false)
             ->assertJsonPath('data.period', '2026-10');
    }

    public function test_report_reflects_approval_state_from_admin_approval(): void
    {
        $admin    = User::factory()->administrator()->create();
        $employee = User::factory()->mitarbeiter()->create();
        $this->seedExecutionData($employee);

        app(\App\Services\MonthlyReportEngineService::class)->approve($employee, 2026, 10, $admin);

        $this->actingWithToken($employee)
             ->getJson('/api/v1/monthly-reports?year=2026&month=10')
             ->assertOk()
             ->assertJsonPath('data.is_approved', true)
             ->assertJsonPath('data.approved_by', $admin->name);

        $this->assertNotNull(\Illuminate\Support\Facades\DB::table('monthly_reports')->first()->snapshot);
    }

    public function test_line_items_endpoint_returns_per_execution_breakdown(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->seedExecutionData($employee);

        $this->actingWithToken($employee)
             ->getJson('/api/v1/monthly-reports/line-items?year=2026&month=10')
             ->assertOk()
             ->assertJsonCount(2, 'data');
    }

    public function test_employee_cannot_view_reports_without_ability(): void
    {
        $employee       = User::factory()->mitarbeiter()->create();
        $employee->tokens()->delete(); // clear default abilities

        // Attach a token that lacks the reports:view ability
        $token = $employee->createToken('limited', ['jobs:view']);

        $this->withToken($token->plainTextToken)
             ->getJson('/api/v1/monthly-reports?year=2026&month=10')
             ->assertForbidden();
    }

    public function test_report_validation_rejects_invalid_month(): void
    {
        $employee = User::factory()->mitarbeiter()->create();

        $this->actingWithToken($employee)
             ->getJson('/api/v1/monthly-reports?year=2026&month=13')
             ->assertStatus(422);
    }
}