<?php

namespace Tests\Unit\Services;

use App\Enums\AdjustmentStatusEnum;
use App\Enums\AuditEventEnum;
use App\Enums\ExecutionStatusEnum;
use App\Models\AuditLog;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObject;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Models\TimeAdjustmentRequest;
use App\Models\User;
use App\Services\TimeAdjustmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeAdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private TimeAdjustmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TimeAdjustmentService::class);
    }

    public function test_approve_records_hours_adjusted_audit_for_fix_execution(): void
    {
        $fo       = FixObject::factory()->withContractHours(2.0)->create();
        $schedule = FixObjectSchedule::factory()->create(['fix_object_id' => $fo->id]);
        $admin    = User::factory()->administrator()->create();
        $employee = User::factory()->mitarbeiter()->create();

        FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $employee->id,
            'actual_start'           => now()->subHours(2),
            'actual_end'             => now(),
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 0,
        ]);

        $request = TimeAdjustmentRequest::create([
            'employee_id'           => $employee->id,
            'job_type'              => 'fix_object',
            'job_id'                => $schedule->id,
            'requested_start'       => CarbonImmutable::now()->subHours(3),
            'requested_end'         => CarbonImmutable::now()->subHour(),
            'original_start'        => now()->subHours(2),
            'original_end'          => now(),
            'reason'                => 'Zeiten korrigieren',
            'status'                => AdjustmentStatusEnum::Pending,
        ]);

        $this->service->approve($request, $admin, 'geprüft');

        $log = AuditLog::where('event', AuditEventEnum::HoursAdjusted->value)
                       ->where('auditable_type', FixObjectExecution::class)
                       ->first();

        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->user_id);
        $this->assertNotNull($log->reason);
        $this->assertEquals('Zeitkorrektur genehmigt (Fixobjekt)', $log->reason);
    }

    public function test_approve_records_hours_adjusted_audit_for_extra_execution(): void
    {
        $admin    = User::factory()->administrator()->create();
        $employee = User::factory()->mitarbeiter()->create();
        $order    = ExtraAuftrag::factory()->create(['created_by' => $admin->id]);

        ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $employee->id,
            'work_start'       => now()->subHours(2),
            'work_end'         => now(),
            'work_minutes'     => 120,
            'paid_minutes'     => 120,
            'status'           => \App\Enums\ExtraExecutionStatusEnum::Completed,
        ]);

        $request = TimeAdjustmentRequest::create([
            'employee_id'           => $employee->id,
            'job_type'              => 'extra_auftrag',
            'job_id'                => $order->id,
            'requested_start'       => CarbonImmutable::now()->subHours(3),
            'requested_end'         => CarbonImmutable::now()->subHour(),
            'original_start'        => now()->subHours(2),
            'original_end'          => now(),
            'reason'                => 'Falsche Zeiten',
            'status'                => AdjustmentStatusEnum::Pending,
        ]);

        $this->service->approve($request, $admin);

        $log = AuditLog::where('event', AuditEventEnum::HoursAdjusted->value)
                       ->where('auditable_type', ExtraAuftragExecution::class)
                       ->first();

        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->user_id);
        $this->assertEquals(120, $log->new_values['paid_minutes']); // 2h neue Arbeit, keine Fahrt
        $this->assertArrayHasKey('work_start', $log->new_values);
    }
}