<?php

namespace Tests\Unit\Services;

use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragAssignee;
use App\Models\FixObjectAssignment;
use App\Models\FixObjectSchedule;
use App\Models\FixObjectScheduleAssignment;
use App\Models\PersonalAppointment;
use App\Models\User;
use App\Services\ReassignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReassignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReassignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReassignmentService::class);
    }

    public function test_schedule_override_redirects_execution_employee(): void
    {
        $fromEmp = User::factory()->mitarbeiter()->create();
        $toEmp   = User::factory()->mitarbeiter()->create();

        $schedule = FixObjectSchedule::factory()->forDate('2026-10-01')->create([
            'scheduled_start' => '07:00:00',
            'scheduled_end'   => '09:00:00',
        ]);

        FixObjectAssignment::create([
            'fix_object_id' => $schedule->fix_object_id,
            'user_id'       => $fromEmp->id,
            'assigned_from' => '2026-01-01',
        ]);

        $assignment = $this->service->reassignSchedule($schedule, $toEmp->id, 'Urlaub');

        $this->assertEquals($toEmp->id, $assignment->user_id);
        $this->assertEquals(
            [$toEmp->id],
            $schedule->fresh(['scheduleAssignments'])->effectiveEmployeeIds(),
        );
    }

    public function test_schedule_reassign_blocks_on_conflict(): void
    {
        $toEmp = User::factory()->mitarbeiter()->create();
        $schedule = FixObjectSchedule::factory()->forDate('2026-10-01')->create([
            'scheduled_start' => '07:00:00',
            'scheduled_end'   => '09:00:00',
        ]);
        FixObjectAssignment::create([
            'fix_object_id' => $schedule->fix_object_id,
            'user_id'       => User::factory()->mitarbeiter()->create()->id,
            'assigned_from' => '2026-01-01',
        ]);

        // toEmp is already booked at 07:30–10:00 that day
        PersonalAppointment::create([
            'user_id'  => $toEmp->id,
            'title'    => 'Busy',
            'start_at' => '2026-10-01 07:30:00',
            'end_at'   => '2026-10-01 10:00:00',
        ]);

        try {
            $this->service->reassignSchedule($schedule, $toEmp->id, null, false);
            $this->fail('Expected ValidationException on conflict');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('conflicts', $e->errors());
        }

        $this->assertEquals(0, FixObjectScheduleAssignment::count());
    }

    public function test_schedule_reassign_forced_wins(): void
    {
        $toEmp = User::factory()->mitarbeiter()->create();
        $schedule = FixObjectSchedule::factory()->forDate('2026-10-01')->create([
            'scheduled_start' => '07:00:00',
            'scheduled_end'   => '09:00:00',
        ]);
        FixObjectAssignment::create([
            'fix_object_id' => $schedule->fix_object_id,
            'user_id'       => User::factory()->mitarbeiter()->create()->id,
            'assigned_from' => '2026-01-01',
        ]);

        PersonalAppointment::create([
            'user_id'  => $toEmp->id,
            'title'    => 'Busy',
            'start_at' => '2026-10-01 07:30:00',
            'end_at'   => '2026-10-01 10:00:00',
        ]);

        $assignment = $this->service->reassignSchedule($schedule, $toEmp->id, 'Notfall', true);

        $this->assertEquals($toEmp->id, $assignment->user_id);
        $this->assertEquals(1, \App\Models\AdminNotification::count());
    }

    public function test_contract_reassign_closes_old_windows(): void
    {
        $oldEmp = User::factory()->mitarbeiter()->create();
        $newEmp = User::factory()->mitarbeiter()->create();
        $fix = \App\Models\FixObject::factory()->create([
            'valid_from' => '2026-01-01',
            'valid_until'=> null,
        ]);

        $oldAssignment = FixObjectAssignment::create([
            'fix_object_id' => $fix->id,
            'user_id'       => $oldEmp->id,
            'assigned_from' => '2026-01-01',
            'assigned_until'=> null,
        ]);

        $newAssignment = $this->service->reassignContract($fix, $newEmp->id, '2026-10-01');

        $this->assertEquals($newEmp->id, $newAssignment->user_id);
        $this->assertEquals('2026-09-30', $oldAssignment->fresh()->assigned_until->toDateString());
    }

    public function test_contract_reassign_blocks_conflict_without_force(): void
    {
        $newEmp = User::factory()->mitarbeiter()->create();
        $fix = \App\Models\FixObject::factory()->create();
        \App\Models\FixObjectAssignment::create([
            'fix_object_id' => $fix->id,
            'user_id'       => User::factory()->mitarbeiter()->create()->id,
            'assigned_from' => '2026-01-01',
        ]);
        FixObjectSchedule::factory()->forDate(now()->addDay()->toDateString())->create([
            'fix_object_id'   => $fix->id,
            'scheduled_start' => '07:00:00',
            'scheduled_end'   => '09:00:00',
        ]);
        PersonalAppointment::create([
            'user_id'  => $newEmp->id,
            'title'    => 'Busy',
            'start_at' => now()->addDay()->toDateString().' 07:30:00',
            'end_at'   => now()->addDay()->toDateString().' 10:00:00',
        ]);

        try {
            $this->service->reassignContract($fix, $newEmp->id, now()->addDay()->toDateString(), null, null, false);
            $this->fail('Expected ValidationException on conflict');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('conflicts', $e->errors());
        }
    }

    public function test_extra_assignee_swap(): void
    {
        $oldEmp = User::factory()->mitarbeiter()->create();
        $newEmp = User::factory()->mitarbeiter()->create();
        $order  = ExtraAuftrag::factory()->create([
            'scheduled_date'        => '2026-10-01',
            'scheduled_time_start'  => null,
        ]);

        $assignee = ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => $oldEmp->id,
            'role_in_order'    => \App\Enums\AssigneeRoleEnum::Member,
        ]);

        $updated = $this->service->reassignExtraAssignee($assignee, $newEmp->id);

        $this->assertEquals($newEmp->id, $updated->user_id);
        $this->assertFalse($order->isAssigned($oldEmp->id));
        $this->assertTrue($order->isAssigned($newEmp->id));
    }

    public function test_extra_assignee_swap_blocks_conflict(): void
    {
        $newEmp = User::factory()->mitarbeiter()->create();
        $order  = ExtraAuftrag::factory()->create([
            'scheduled_date'       => '2026-10-01',
            'scheduled_time_start' => null,
        ]);
        \App\Models\FixObjectSchedule::factory()->forDate('2026-10-01')->create([
            'scheduled_start' => '07:00:00',
            'scheduled_end'   => '09:00:00',
            'fix_object_id'   => \App\Models\FixObject::factory()->create()->id,
        ]);
        \App\Models\FixObjectAssignment::create([
            'fix_object_id' => \App\Models\FixObjectSchedule::first()->fix_object_id,
            'user_id'       => $newEmp->id,
            'assigned_from' => '2026-01-01',
        ]);

        $assignee = ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => User::factory()->mitarbeiter()->create()->id,
            'role_in_order'    => \App\Enums\AssigneeRoleEnum::Member,
        ]);

        try {
            $this->service->reassignExtraAssignee($assignee, $newEmp->id, false);
            $this->fail('Expected ValidationException on conflict');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('conflicts', $e->errors());
        }
    }
}