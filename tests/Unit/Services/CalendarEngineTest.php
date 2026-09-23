<?php

namespace Tests\Unit\Services;

use App\Models\ExtraAuftrag;
use App\Models\FixObjectAssignment;
use App\Models\FixObjectSchedule;
use App\Models\InternalEvent;
use App\Models\InternalEventAssignee;
use App\Models\PersonalAppointment;
use App\Models\EmployeeShift;
use App\Models\User;
use App\Services\CalendarEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEngineTest extends TestCase
{
    use RefreshDatabase;

    private CalendarEngineService $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(CalendarEngineService::class);
    }

    public function test_fix_schedule_appears_with_contract_employee(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $schedule = FixObjectSchedule::factory()->forDate('2026-10-05')->create(['scheduled_start' => '07:00:00', 'scheduled_end' => '09:00:00']);

        FixObjectAssignment::create([
            'fix_object_id' => $schedule->fix_object_id,
            'user_id'       => $employee->id,
            'assigned_from' => '2026-01-01',
        ]);

        $events = $this->engine->forRange(now()->startOfMonth(), now()->addMonths(2)->endOfMonth());

        $fixEvents = $events->where('id', "fix_schedule:{$schedule->id}");

        $this->assertCount(1, $fixEvents);
        $this->assertEquals([$employee->id], $fixEvents->first()->employeeIds);
        $this->assertFalse($fixEvents->first()->allDay);
    }

    public function test_schedule_override_replaces_contract_employee(): void
    {
        $contractEmp = User::factory()->mitarbeiter()->create();
        $overrideEmp = User::factory()->mitarbeiter()->create();

        $schedule = FixObjectSchedule::factory()->forDate('2026-10-06')->create(['scheduled_start' => '07:00:00', 'scheduled_end' => '09:00:00']);

        FixObjectAssignment::create([
            'fix_object_id' => $schedule->fix_object_id,
            'user_id'       => $contractEmp->id,
            'assigned_from' => '2026-01-01',
        ]);

        \App\Models\FixObjectScheduleAssignment::create([
            'schedule_id' => $schedule->id,
            'user_id'     => $overrideEmp->id,
            'created_by'  => User::factory()->administrator()->create()->id,
        ]);

        $events = $this->engine->forDay(\Carbon\Carbon::parse('2026-10-06'));

        $fixEvent = $events->firstWhere('id', "fix_schedule:{$schedule->id}");

        $this->assertNotNull($fixEvent);
        $this->assertEquals([$overrideEmp->id], $fixEvent->employeeIds);
    }

    public function test_all_day_extra_order_uses_date_window(): void
    {
        $order = ExtraAuftrag::factory()->create([
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time_start' => null,
        ]);

        $events = $this->engine->forDay(now()->addDay());

        $event = $events->firstWhere('id', "extra_auftrag:{$order->id}");

        $this->assertNotNull($event);
        $this->assertTrue($event->allDay);
    }

    public function test_shift_personal_and_internal_layers_aggregate(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $admin = User::factory()->administrator()->create();

        EmployeeShift::factory()->count(1)->state(['user_id' => $user->id])->create();
        PersonalAppointment::factory()->count(1)->state(['user_id' => $user->id])->create();

        $event = InternalEvent::factory()->state(['created_by' => $admin->id])->create();
        InternalEventAssignee::create(['internal_event_id' => $event->id, 'user_id' => $user->id]);

        $events = $this->engine->forRange(now()->startOfMonth(), now()->addMonths(2)->endOfMonth());

        $this->assertGreaterThanOrEqual(1, $events->filter(fn($e) => $e->layer === 'shifts')->count());
        $this->assertGreaterThanOrEqual(1, $events->filter(fn($e) => $e->layer === 'personal')->count());
        $this->assertGreaterThanOrEqual(1, $events->filter(fn($e) => $e->layer === 'internal')->count());

        $internalEvents = $events->where('layer', 'internal');
        $this->assertTrue(in_array($user->id, $internalEvents->first()->employeeIds, true));
    }

    public function test_employee_filter_returns_only_their_events(): void
    {
        $user = User::factory()->mitarbeiter()->create();

        $schedule = FixObjectSchedule::factory()->create();
        FixObjectAssignment::create(['fix_object_id' => $schedule->fix_object_id, 'user_id' => $user->id, 'assigned_from' => '2026-01-01']);

        $events = $this->engine->forRange(now()->startOfMonth(), now()->addMonths(2)->endOfMonth(), [], $user->id);

        $this->assertTrue($events->isNotEmpty());
        foreach ($events as $event) {
            $this->assertTrue(in_array($user->id, $event->employeeIds, true));
        }
    }

    public function test_layer_filter_excludes_untoggled_layers(): void
    {
        User::factory()->mitarbeiter()->create();

        FixObjectSchedule::factory()->count(2)->create();

        PersonalAppointment::factory()->count(2)->state(['user_id' => User::factory()->mitarbeiter()->create()->id])->create();

        $events = $this->engine->forRange(now()->startOfMonth(), now()->addMonths(2)->endOfMonth(), ['shifts']);

        $this->assertCount(0, $events->whereIn('layer', ['fix', 'shifts', 'personal', 'internal', 'teamup'])->where('layer', 'fix'));
        $this->assertTrue($events->where('layer', 'shifts')->isEmpty());
    }
}