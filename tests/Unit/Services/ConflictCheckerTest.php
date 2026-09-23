<?php

namespace Tests\Unit\Services;

use App\Enums\AdminNotificationTypeEnum;
use App\Models\AdminNotification;
use App\Models\PersonalAppointment;
use App\Models\EmployeeShift;
use App\Models\User;
use App\Services\ConflictCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConflictCheckerTest extends TestCase
{
    use RefreshDatabase;

    private ConflictCheckerService $conflicts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conflicts = app(ConflictCheckerService::class);
    }

    private function booking(User $user, string $from, string $to, string $prefix = 'own'): \App\Models\PersonalAppointment
    {
        return PersonalAppointment::create([
            'user_id'  => $user->id,
            'title'    => "Test {$prefix}",
            'start_at' => $from,
            'end_at'   => $to,
            'all_day'  => false,
        ]);
    }

    public function test_overlapping_same_employee_is_a_conflict(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->booking($employee, '2026-10-01 08:00:00', '2026-10-01 10:00:00', 'existing');

        $conflicts = $this->conflicts->findWindowConflicts(
            [$employee->id],
            new \Carbon\Carbon('2026-10-01 09:00:00'),
            new \Carbon\Carbon('2026-10-01 11:00:00'),
        );

        $this->assertCount(1, $conflicts);
        $this->assertEquals($employee->id, $conflicts->first()->employeeId);
    }

    public function test_non_overlapping_same_employee_is_clean(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->booking($employee, '2026-10-01 06:00:00', '2026-10-01 07:00:00', 'existing');

        $conflicts = $this->conflicts->findWindowConflicts(
            [$employee->id],
            new \Carbon\Carbon('2026-10-01 09:00:00'),
            new \Carbon\Carbon('2026-10-01 11:00:00'),
        );

        $this->assertCount(0, $conflicts);
    }

    public function test_different_employees_never_conflict(): void
    {
        $a = User::factory()->mitarbeiter()->create();
        $b = User::factory()->mitarbeiter()->create();
        $this->booking($a, '2026-10-01 08:00:00', '2026-10-01 10:00:00', 'existing');

        $conflicts = $this->conflicts->findWindowConflicts(
            [$b->id],
            new \Carbon\Carbon('2026-10-01 09:00:00'),
            new \Carbon\Carbon('2026-10-01 11:00:00'),
        );

        $this->assertCount(0, $conflicts);
    }

    public function test_ignore_list_skips_own_event(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $own = $this->booking($employee, '2026-10-01 08:00:00', '2026-10-01 10:00:00', 'own');

        $conflicts = $this->conflicts->findWindowConflicts(
            [$employee->id],
            new \Carbon\Carbon('2026-10-01 09:00:00'),
            new \Carbon\Carbon('2026-10-01 11:00:00'),
            false,
            ["personal_appointment:{$own->id}"],
        );

        $this->assertCount(0, $conflicts);
    }

    public function test_shift_conflicts_with_fix_booking(): void
    {
        $employee = User::factory()->mitarbeiter()->create();

        \App\Models\FixObjectSchedule::factory()->forDate('2026-10-01')->create([
            'fix_object_id'   => \App\Models\FixObject::factory()->create()->id,
            'scheduled_start' => '07:00:00',
            'scheduled_end'   => '09:00:00',
        ]);

        \App\Models\FixObjectAssignment::create([
            'fix_object_id' => \App\Models\FixObjectSchedule::first()->fix_object_id,
            'user_id'       => $employee->id,
            'assigned_from' => '2026-01-01',
        ]);

        $conflicts = $this->conflicts->findWindowConflicts(
            [$employee->id],
            new \Carbon\Carbon('2026-10-01 08:00:00'),
            new \Carbon\Carbon('2026-10-01 10:00:00'),
        );

        $this->assertCount(1, $conflicts);
    }

    public function test_assert_clean_blocks_and_persists_notification(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->booking($employee, '2026-10-01 08:00:00', '2026-10-01 10:00:00', 'existing');

        $conflicts = $this->conflicts->findWindowConflicts(
            [$employee->id],
            new \Carbon\Carbon('2026-10-01 09:00:00'),
            new \Carbon\Carbon('2026-10-01 11:00:00'),
        );

        try {
            $this->conflicts->assertClean($conflicts);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('conflicts', $e->errors());
        }

        $notification = AdminNotification::first();
        $this->assertNotNull($notification);
        $this->assertEquals(AdminNotificationTypeEnum::Conflict, $notification->type);
        $this->assertTrue($notification->payload !== []);
    }

    public function test_force_allows_override_but_still_notifies(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->booking($employee, '2026-10-01 08:00:00', '2026-10-01 10:00:00', 'existing');

        $conflicts = $this->conflicts->findWindowConflicts(
            [$employee->id],
            new \Carbon\Carbon('2026-10-01 09:00:00'),
            new \Carbon\Carbon('2026-10-01 11:00:00'),
        );

        $this->conflicts->assertClean($conflicts, force: true);

        $this->assertEquals(1, AdminNotification::count());
    }

    public function test_annotate_marks_only_overlapping_events(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->booking($employee, '2026-10-01 08:00:00', '2026-10-01 10:00:00', 'existing');
        $this->booking($employee, '2026-10-01 09:00:00', '2026-10-01 11:00:00', 'overlap');
        $this->booking($employee, '2026-10-01 13:00:00', '2026-10-01 15:00:00', 'free');

        $events = app(\App\Services\CalendarEngineService::class)->forDay(new \Carbon\Carbon('2026-10-01'), ['personal']);

        $annotated = $this->conflicts->annotate($events);

        $this->assertTrue($annotated->firstWhere('title', 'Test existing')->hasConflicts());
        $this->assertTrue($annotated->firstWhere('title', 'Test overlap')->hasConflicts());
        $this->assertFalse($annotated->firstWhere('title', 'Test free')->hasConflicts());
    }
}