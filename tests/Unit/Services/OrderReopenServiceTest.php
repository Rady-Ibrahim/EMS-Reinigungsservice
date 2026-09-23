<?php

namespace Tests\Unit\Services;

use App\Enums\ExecutionStatusEnum;
use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Enums\ScheduleStatusEnum;
use App\Enums\AuditEventEnum;
use App\Models\AuditLog;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\OrderReopenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderReopenServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderReopenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderReopenService::class);
    }

    // ── Extra-Auftrag ──────────────────────────────────────────────────────

    public function test_reopen_extra_resets_order_and_executions(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = ExtraAuftrag::factory()->completed()->create();

        $execution = ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $admin->id,
            'work_start'       => now()->subHours(3),
            'work_end'         => now(),
            'work_minutes'     => 180,
            'paid_minutes'     => 150,
            'status'           => ExtraExecutionStatusEnum::Completed,
        ]);

        $reopened = $this->service->reopenExtra($order, $admin, 'Falsch abgerechnet');

        $this->assertEquals(ExtraAuftragStatusEnum::Assigned, $reopened->status);

        $exec = $execution->fresh();
        $this->assertEquals(ExtraExecutionStatusEnum::Working, $exec->status);
        $this->assertNull($exec->work_end);
    }

    public function test_reopen_extra_audits_with_reason(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = ExtraAuftrag::factory()->completed()->create();

        $this->service->reopenExtra($order, $admin, 'Korrektur durchgeführt');

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ExtraAuftrag::class,
            'auditable_id'   => $order->id,
            'event'          => AuditEventEnum::Reopened->value,
            'reason'         => 'Korrektur durchgeführt',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type'    => 'job_reopened',
        ]);
    }

    public function test_reopen_extra_rejects_non_terminal_order(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = ExtraAuftrag::factory()->assigned()->create();

        $this->expectException(ValidationException::class);
        $this->service->reopenExtra($order, $admin, 'Warum auch immer');
    }

    public function test_reopen_extra_rejects_cancelled_order(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = ExtraAuftrag::factory()->create([
            'status'           => ExtraAuftragStatusEnum::Cancelled,
            'cancelled_reason' => 'Nicht verfügbar',
        ]);

        $this->expectException(ValidationException::class);
        $this->service->reopenExtra($order, $admin, 'Rückgängig machen');
    }

    public function test_reopen_extra_requires_reason(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = ExtraAuftrag::factory()->completed()->create();

        $this->expectException(ValidationException::class);
        $this->service->reopenExtra($order, $admin, '');
    }

    // ── Fixobjekt ──────────────────────────────────────────────────────────

    public function test_reopen_fix_schedule_resets_execution(): void
    {
        $admin    = User::factory()->administrator()->create();
        $schedule = FixObjectSchedule::factory()->completed()->create();

        $execution = FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => $admin->id,
            'actual_start'           => now()->subHours(2),
            'actual_end'             => now(),
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 2.0,
        ]);

        $reopened = $this->service->reopenFix($schedule, $admin, 'Erneut reinigen');

        $this->assertEquals(ScheduleStatusEnum::InProgress, $reopened->status);

        $exec = $execution->fresh();
        $this->assertEquals(ExecutionStatusEnum::PhotosAfter, $exec->status);
        $this->assertNull($exec->actual_end);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => FixObjectSchedule::class,
            'auditable_id'   => $schedule->id,
            'event'          => AuditEventEnum::Reopened->value,
            'reason'         => 'Erneut reinigen',
        ]);
    }

    public function test_reopen_fix_rejects_pending_schedule(): void
    {
        $admin    = User::factory()->administrator()->create();
        $schedule = FixObjectSchedule::factory()->create();

        $this->expectException(ValidationException::class);
        $this->service->reopenFix($schedule, $admin, 'Test');
    }
}