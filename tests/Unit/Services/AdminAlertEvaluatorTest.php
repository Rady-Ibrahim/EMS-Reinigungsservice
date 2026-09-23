<?php

namespace Tests\Unit\Services;

use App\Enums\ExecutionStatusEnum;
use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Models\AdminNotification;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObjectAssignment;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AdminAlertEvaluator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAlertEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private AdminAlertEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        config(['alerts.min_monthly_hours_percent' => 1.0]);
        $this->evaluator = app(AdminAlertEvaluator::class);
    }

    // ── Missing photos ─────────────────────────────────────────────────────

    public function test_extra_execution_stuck_at_photos_after_triggers_alert(): void
    {
        $order = ExtraAuftrag::factory()->assigned()->create();

        ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => User::factory()->mitarbeiter()->create()->id,
            'status'           => ExtraExecutionStatusEnum::PhotosAfter,
        ]);

        $created = $this->evaluator->evaluateMissingPhotos();

        $this->assertEquals(1, $created);
        $this->assertDatabaseHas('admin_notifications', [
            'title'      => "Nachher-Fotos fehlen: Auftrag #{$order->id}",
            'dedupe_key' => 'photos_missing:extra_exec:1',
        ]);
    }

    public function test_extra_execution_with_photos_does_not_trigger_alert(): void
    {
        $order = ExtraAuftrag::factory()->assigned()->create();

        ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => User::factory()->mitarbeiter()->create()->id,
            'status'           => ExtraExecutionStatusEnum::PhotosAfter,
            'after_photos'     => ['photo-a.jpg'],
        ]);

        $this->assertEquals(0, $this->evaluator->evaluateMissingPhotos());
    }

    public function test_fix_execution_completed_without_photos_triggers_alert(): void
    {
        $schedule = FixObjectSchedule::factory()->completed()->create();

        FixObjectExecution::create([
            'schedule_id'            => $schedule->id,
            'user_id'                => User::factory()->mitarbeiter()->create()->id,
            'actual_start'           => now()->subHours(2),
            'actual_end'             => now(),
            'status'                 => ExecutionStatusEnum::Completed,
            'contract_hours_applied' => 2.0,
        ]);

        $this->assertEquals(1, $this->evaluator->evaluateMissingPhotos());
    }

    public function test_alerts_are_deduplicated_within_24h(): void
    {
        $order = ExtraAuftrag::factory()->assigned()->create();

        ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => User::factory()->mitarbeiter()->create()->id,
            'status'           => ExtraExecutionStatusEnum::PhotosAfter,
        ]);

        $this->evaluator->evaluateMissingPhotos();
        $secondRun = $this->evaluator->evaluateMissingPhotos();

        $this->assertEquals(0, $secondRun);
        $this->assertEquals(1, AdminNotification::count());
    }

    // ── Unclosed orders ────────────────────────────────────────────────────

    public function test_overdue_extra_order_triggers_alert(): void
    {
        $order = ExtraAuftrag::factory()->assigned()->create([
            'scheduled_date' => now()->subDays(2)->toDateString(),
        ]);

        $created = $this->evaluator->evaluateUnclosed();

        $this->assertEquals(1, $created);
        $this->assertDatabaseHas('admin_notifications', ['dedupe_key' => 'unclosed:extra:'.$order->id]);
    }

    public function test_future_order_does_not_trigger_unclosed_alert(): void
    {
        ExtraAuftrag::factory()->assigned()->create([
            'scheduled_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->assertEquals(0, $this->evaluator->evaluateUnclosed());
    }

    // ── Monthly hours ──────────────────────────────────────────────────────

    public function test_employee_without_bookings_triggers_monthly_hours_alert(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fix = \App\Models\FixObject::factory()->daily()->withContractHours(3.0)->create();

        FixObjectAssignment::create([
            'fix_object_id' => $fix->id,
            'user_id'       => $employee->id,
            'assigned_from' => $fix->valid_from->toDateString(),
        ]);

        $created = $this->evaluator->evaluateMonthlyHours(Carbon::now()->startOfMonth());

        $this->assertEquals(1, $created);

        $adminNote = AdminNotification::where('dedupe_key', 'like', 'monthly_hours:'.$employee->id.':%')->first();
        $this->assertNotNull($adminNote);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type'    => 'alert',
            'title'   => 'Unterbuchung '.Carbon::now()->startOfMonth()->format('m/Y'),
        ]);
    }

    public function test_employee_meeting_planned_hours_does_not_trigger(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $fix = \App\Models\FixObject::factory()->daily()->withContractHours(3.0)->create();

        FixObjectAssignment::create([
            'fix_object_id' => $fix->id,
            'user_id'       => $employee->id,
            'assigned_from' => $fix->valid_from->toDateString(),
        ]);

        // Book enough paid hours for every occurrence via contract hours.
        $month = Carbon::now()->startOfMonth();
        $occurrences = app(\App\Services\ContractHoursCalculator::class)
            ->countOccurrences($fix, (int) $month->format('Y'), (int) $month->format('n'));

        $executions = [];
        for ($i = 0; $i < $occurrences; $i++) {
            $date = $month->copy()->addDay($i);
            $executions[] = [
                'schedule_id'            => FixObjectSchedule::factory()->create([
                    'fix_object_id'  => $fix->id,
                    'scheduled_date' => $date->toDateString(),
                ])->id,
                'user_id'                => $employee->id,
                'actual_start'           => $date->setTime(8, 0)->format('Y-m-d H:i:s'),
                'actual_end'             => $date->copy()->setTime(11, 0)->format('Y-m-d H:i:s'),
                'status'                 => ExecutionStatusEnum::Completed->value,
                'contract_hours_applied' => 3.0,
            ];
        }
        FixObjectExecution::insert($executions);

        $created = $this->evaluator->evaluateMonthlyHours($month);

        $this->assertEquals(0, $created);
    }
}