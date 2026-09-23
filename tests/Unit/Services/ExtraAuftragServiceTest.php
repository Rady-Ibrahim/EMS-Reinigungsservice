<?php

namespace Tests\Unit\Services;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragAssignee;
use App\Models\ExtraAuftragExecution;
use App\Models\User;
use App\Services\ExtraAuftragService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExtraAuftragServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExtraAuftragService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ExtraAuftragService::class);
    }

    private function prepareOrder(array $overrides = [], ?\Carbon\Carbon $workStart = null): array
    {
        $leader = User::factory()->vorarbeiter()->create();
        $order  = ExtraAuftrag::factory()->assigned()->create($overrides);

        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $leader->id,
            'role_in_order'    => AssigneeRoleEnum::Leader,
        ]);

        $checklist = array_map(
            fn(array $item) => ['task' => $item['task'], 'completed' => true],
            $order->checklist_template ?? []
        );

        $execution = ExtraAuftragExecution::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $leader->id,
            'status'           => ExtraExecutionStatusEnum::Working,
            'work_start'       => $workStart ?? now()->subHour(),
            'before_photos'    => ['before-1.jpg'],
            'after_photos'     => ['after-1.jpg'],
            'checklist_items'  => $checklist,
        ]);

        return [$leader, $order, $execution];
    }

    public function test_complete_order_requires_gps_end_when_work_started_with_gps(): void
    {
        [$leader, $order, $execution] = $this->prepareOrder();
        $execution->update([
            'gps_work_start_lat' => 48.13715,
            'gps_work_start_lng' => 11.57612,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->completeOrder($order, $leader->id, [
            'work_end' => now()->toIso8601String(),
        ]);
    }

    public function test_complete_order_gps_closing_works_when_gps_end_provided(): void
    {
        [$leader, $order] = $this->prepareOrder([
            'estimated_hours' => 4.0,
        ]);
        $order->executions->first()->update([
            'gps_work_start_lat' => 48.13715,
            'gps_work_start_lng' => 11.57612,
        ]);

        $completed = $this->service->completeOrder($order, $leader->id, [
            'work_end'        => now()->toIso8601String(),
            'gps_work_end_lat' => 48.20000,
            'gps_work_end_lng' => 11.60000,
        ]);

        $this->assertEquals('completed', $completed->status->value);
    }

    public function test_complete_order_blocks_time_anomaly_when_work_exceeds_2x_estimate(): void
    {
        [$leader, $order] = $this->prepareOrder(
            overrides: ['estimated_hours' => 1.0], // 1h planned
            workStart: now()->subHours(3)           // but 3h actually worked
        );

        $this->expectException(ValidationException::class);

        $this->service->completeOrder($order, $leader->id, [
            'work_end' => now()->toIso8601String(),
        ]);
    }

    public function test_complete_order_succeeds_with_normal_duration(): void
    {
        [$leader, $order] = $this->prepareOrder([
            'estimated_hours' => 4.0,
        ]);

        $completed = $this->service->completeOrder($order, $leader->id, [
            'work_end' => now()->toIso8601String(),
        ]);

        $this->assertEquals('completed', $completed->status->value);
    }
}