<?php

namespace Tests\Feature\Admin;

use App\Enums\FixFrequencyEnum;
use App\Models\FixObjectAssignment;
use App\Models\FixObject;
use App\Models\FixObjectSchedule;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\EmployeeShift;
use App\Models\ExtraAuftrag;
use App\Models\InternalEvent;
use App\Models\PersonalAppointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->administrator()->create());
    }

    public function test_admin_can_view_calendar_page(): void
    {
        $this->get('/admin/calendar')
             ->assertOk()
             ->assertSee('Kalender')
             ->assertSee("initialView: 'dayGridMonth'", false)
             ->assertSee('html[dir="rtl"] .slide-panel:not(.open)', false)
             ->assertSee('value="fix"')
             ->assertSee('value="extra"');
    }

    public function test_personal_appointment_form_lists_only_administrators(): void
    {
        User::factory()->administrator()->create(['name' => 'Calendar Admin']);
        $employee = User::factory()->mitarbeiter()->create(['name' => 'Calendar Employee']);

        $this->get(route('admin.calendar.appointments.create'))
            ->assertOk()
            ->assertSee('Calendar Admin')
            ->assertDontSee('Calendar Employee');
    }

    public function test_personal_appointment_cannot_be_assigned_to_an_employee(): void
    {
        $employee = User::factory()->mitarbeiter()->create();

        $this->post(route('admin.calendar.appointments.store'), [
            'user_id' => $employee->id,
            'title' => 'Employee personal appointment',
            'start_at' => now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i'),
            'end_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('personal_appointments', [
            'user_id' => $employee->id,
            'title' => 'Employee personal appointment',
        ]);
    }

    public function test_calendar_page_displays_employee_filter_with_assigned_color(): void
    {
        $employee = User::factory()->vorarbeiter()->create(['name' => 'Calendar Lead']);
        $color = $employee->employeeProfile->calendar_color;

        $this->get('/admin/calendar')
            ->assertOk()
            ->assertSee('Calendar Lead')
            ->assertSee($color, false);
    }

    public function test_events_feed_returns_fix_and_personal_events(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $schedule = FixObjectSchedule::factory()->forDate(now()->addDay()->toDateString())->create(['scheduled_start' => '07:00:00', 'scheduled_end' => '09:00:00']);
        FixObjectAssignment::create(['fix_object_id' => $schedule->fix_object_id, 'user_id' => $employee->id, 'assigned_from' => now()->subDay()->toDateString()]);

        PersonalAppointment::create([
            'user_id'  => $employee->id,
            'title'    => 'Zahnarzt',
            'start_at' => now()->addDay()->toDateString().' 12:00:00',
            'end_at'   => now()->addDay()->toDateString().' 13:00:00',
            'location' => 'Praxis am Markt',
        ]);

        $from = now()->toDateString();
        $to   = now()->addDays(3)->toDateString();

        $response = $this->getJson("/admin/calendar/events?from={$from}&to={$to}&layers=fix,personal")
            ->assertOk();

        $ids = collect($response->json('events'))->pluck('id')->all();
        $this->assertContains("fix_schedule:{$schedule->id}", $ids);
        $this->assertCount(1, collect($response->json('events'))->where('layer', 'personal'));
        $personalEvent = collect($response->json('events'))->firstWhere('layer', 'personal');
        $this->assertSame('Zahnarzt', $personalEvent['title']);
        $this->assertSame('Praxis am Markt', $personalEvent['location']);
        $this->assertSame($employee->id, $personalEvent['owner_id']);
    }

    public function test_calendar_feed_generates_missing_fix_schedules_for_active_contracts(): void
    {
        $fixObject = FixObject::factory()->create([
            'frequency' => FixFrequencyEnum::Daily,
            'frequency_days' => [],
            'valid_from' => now()->startOfMonth()->toDateString(),
        ]);

        $from = now()->toDateString();
        $response = $this->getJson("/admin/calendar/events?from={$from}&to={$from}&layers=fix")
            ->assertOk();

        $schedule = FixObjectSchedule::where('fix_object_id', $fixObject->id)
            ->whereDate('scheduled_date', $from)
            ->first();

        $this->assertNotNull($schedule);
        $response->assertJsonFragment([
            'id' => "fix_schedule:{$schedule->id}",
            'title' => $fixObject->title,
            'layer' => 'fix',
        ]);
    }

    public function test_calendar_feed_includes_fixed_object_schedule_and_edit_link_for_requested_date(): void
    {
        $date = now()->subDay()->toDateString();
        $fixObject = FixObject::factory()->create([
            'frequency' => FixFrequencyEnum::Daily,
            'frequency_days' => [],
            'valid_from' => now()->subMonth()->toDateString(),
        ]);

        $response = $this->getJson(route('admin.calendar.events', [
            'from' => $date,
            'to' => $date,
            'layers' => 'fix',
        ]))->assertOk();

        $schedule = FixObjectSchedule::where('fix_object_id', $fixObject->id)
            ->whereDate('scheduled_date', $date)
            ->first();

        $this->assertNotNull($schedule);
        $event = collect($response->json('events'))->firstWhere('id', "fix_schedule:{$schedule->id}");

        $this->assertNotNull($event, 'The fixed-object schedule should be included in the requested calendar date.');
        $this->assertSame(route('admin.calendar.schedules.edit', $schedule), $event['detail_url']);
    }

    public function test_admin_can_edit_and_mark_a_single_fixed_object_visit(): void
    {
        $schedule = FixObjectSchedule::factory()->forDate('2026-11-02')->create([
            'scheduled_start' => '08:00:00',
            'scheduled_end' => '10:00:00',
        ]);

        $this->get(route('admin.calendar.schedules.edit', $schedule))
            ->assertOk()
            ->assertSee('2026-11-02');

        $this->put(route('admin.calendar.schedules.update', $schedule), [
            'scheduled_date' => '2026-11-03',
            'scheduled_start' => '09:00',
            'scheduled_end' => '11:00',
        ])->assertRedirect(route('admin.calendar.index'));

        $this->assertDatabaseHas('fix_object_schedules', [
            'id' => $schedule->id,
            'scheduled_date' => '2026-11-03',
            'source_date' => '2026-11-02',
            'scheduled_start' => '09:00:00',
            'scheduled_end' => '11:00:00',
            'is_manually_adjusted' => true,
        ]);

        $this->post(route('admin.calendar.marks.store', [
            'eventType' => 'fix_schedule',
            'eventId' => $schedule->id,
        ]))->assertRedirect();

        $eventResponse = $this->getJson(route('admin.calendar.events', [
            'from' => '2026-11-03',
            'to' => '2026-11-03',
            'layers' => 'fix',
        ]))->assertOk();
        $event = collect($eventResponse->json('events'))->firstWhere('id', "fix_schedule:{$schedule->id}");

        $this->assertNotNull($event);
        $this->assertTrue($event['metadata']['is_marked']);
        $this->assertStringStartsWith('★ ', $event['title']);
        $this->assertSame(route('admin.calendar.schedules.edit', $schedule), $event['detail_url']);

        $this->getJson(route('admin.calendar.events', [
            'from' => '2026-11-02',
            'to' => '2026-11-02',
            'layers' => 'fix',
        ]))->assertOk();
        $this->assertDatabaseMissing('fix_object_schedules', [
            'fix_object_id' => $schedule->fix_object_id,
            'scheduled_date' => '2026-11-02',
        ]);

        $this->delete(route('admin.calendar.marks.destroy', [
            'eventType' => 'fix_schedule',
            'eventId' => $schedule->id,
        ]))->assertRedirect();
        $this->assertDatabaseMissing('calendar_event_marks', [
            'event_type' => 'fix_schedule',
            'event_id' => $schedule->id,
        ]);
    }

    public function test_calendar_feed_provides_edit_links_for_each_other_event_type(): void
    {
        $date = '2026-11-12';
        $extra = ExtraAuftrag::factory()->create(['scheduled_date' => $date]);
        $shift = EmployeeShift::factory()->create([
            'start_at' => "{$date} 08:00:00",
            'end_at' => "{$date} 16:00:00",
        ]);
        $appointment = PersonalAppointment::create([
            'user_id' => auth()->id(),
            'title' => 'Personal calendar appointment',
            'start_at' => "{$date} 09:00:00",
            'end_at' => "{$date} 10:00:00",
        ]);
        $internalEvent = InternalEvent::factory()->create([
            'start_at' => "{$date} 11:00:00",
            'end_at' => "{$date} 12:00:00",
        ]);

        $events = $this->getJson(route('admin.calendar.events', [
            'from' => $date,
            'to' => $date,
            'layers' => 'extra,shifts,personal,internal',
        ]))->assertOk()->json('events');

        $this->assertSame(
            route('admin.extra-auftraege.edit', $extra),
            collect($events)->firstWhere('id', "extra_auftrag:{$extra->id}")['detail_url']
        );
        $this->assertSame(
            route('admin.calendar.shifts.edit', $shift),
            collect($events)->firstWhere('id', "employee_shift:{$shift->id}")['detail_url']
        );
        $this->assertSame(
            route('admin.calendar.appointments.edit', $appointment),
            collect($events)->firstWhere('id', "personal_appointment:{$appointment->id}")['detail_url']
        );
        $this->assertSame(
            route('admin.calendar.internal-events.edit', $internalEvent),
            collect($events)->firstWhere('id', "internal_event:{$internalEvent->id}")['detail_url']
        );
    }

    public function test_calendar_week_feed_includes_fixed_object_visit_on_october_twenty_first(): void
    {
        $fixObject = FixObject::factory()->create([
            'valid_from' => '2026-01-01',
        ]);
        $schedule = FixObjectSchedule::factory()->forDate('2026-10-21')->create([
            'fix_object_id' => $fixObject->id,
            'scheduled_start' => '06:00:00',
            'scheduled_end' => '10:00:00',
        ]);

        $response = $this->getJson(route('admin.calendar.events', [
            'from' => '2026-10-19',
            'to' => '2026-10-25',
            'layers' => 'fix,extra,shifts,personal,internal',
        ]))->assertOk();

        $event = collect($response->json('events'))->firstWhere('id', "fix_schedule:{$schedule->id}");
        $this->assertNotNull($event);
        $this->assertStringStartsWith('2026-10-21T06:00:00', $event['start']);
    }

    public function test_calendar_generates_fixed_object_visits_from_contract_weekdays(): void
    {
        $fixObject = FixObject::factory()->create([
            'frequency' => FixFrequencyEnum::Triweekly,
            'frequency_days' => ['Wed', 'Thu', 'Fri'],
            'valid_from' => '2026-10-21',
            'valid_until' => '2026-10-23',
        ]);

        app(\App\Services\ScheduleGeneratorService::class)->generateForRange(
            \Carbon\Carbon::parse('2026-10-21'),
            \Carbon\Carbon::parse('2026-10-23')
        );

        $response = $this->getJson(route('admin.calendar.events', [
            'from' => '2026-10-21',
            'to' => '2026-10-21',
            'layers' => 'fix',
        ]))->assertOk();

        $schedule = FixObjectSchedule::where('fix_object_id', $fixObject->id)
            ->whereDate('scheduled_date', '2026-10-21')
            ->first();

        $this->assertNotNull($schedule);
        $this->assertContains("fix_schedule:{$schedule->id}", collect($response->json('events'))->pluck('id')->all());
    }

    public function test_fix_object_update_requires_weekday_count_to_match_frequency(): void
    {
        $fixObject = FixObject::factory()->create([
            'frequency' => FixFrequencyEnum::Triweekly,
            'frequency_days' => [],
        ]);

        $this->put(route('admin.fix-objects.update', $fixObject), [
            'customer_id' => $fixObject->customer_id,
            'location_id' => $fixObject->location_id,
            'title' => $fixObject->title,
            'frequency' => 'triweekly',
            'frequency_days' => ['Wed', 'Fri'],
            'contract_hours' => 2,
            'time_start' => '06:00',
            'time_end' => '10:00',
            'valid_from' => '2026-10-21',
            'valid_until' => '2026-10-23',
            'is_active' => 1,
            'calendar_color' => '#D4AF37',
        ])->assertSessionHasErrors('frequency_days');
    }

    public function test_effective_employee_ids_falls_back_when_loaded_fix_object_is_missing(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $schedule = FixObjectSchedule::factory()->create();
        FixObjectAssignment::create([
            'fix_object_id' => $schedule->fix_object_id,
            'user_id' => $employee->id,
            'assigned_from' => now()->subDay()->toDateString(),
        ]);
        $schedule->setRelation('fixObject', null);

        $this->assertSame([$employee->id], $schedule->effectiveEmployeeIds());
    }

    public function test_calendar_feed_can_filter_events_by_employee(): void
    {
        $selectedEmployee = User::factory()->mitarbeiter()->create();
        $otherEmployee = User::factory()->mitarbeiter()->create();
        $date = now()->toDateString();

        foreach ([$selectedEmployee, $otherEmployee] as $employee) {
            PersonalAppointment::create([
                'user_id' => $employee->id,
                'title' => "Appointment {$employee->id}",
                'start_at' => "{$date} 12:00:00",
                'end_at' => "{$date} 13:00:00",
            ]);
        }

        $response = $this->getJson(route('admin.calendar.events', [
            'from' => $date,
            'to' => $date,
            'layers' => 'personal',
            'employee_filter' => 1,
            'employees' => [$selectedEmployee->id],
        ]))->assertOk();

        $events = collect($response->json('events'));
        $this->assertCount(1, $events);
        $this->assertSame([$selectedEmployee->id], $events->first()['employee_ids']);
    }

    public function test_calendar_feed_shows_all_fixed_object_events_until_employee_filter_is_narrowed(): void
    {
        $selectedEmployee = User::factory()->mitarbeiter()->create();
        $otherEmployee = User::factory()->mitarbeiter()->create();
        $date = now()->addDay()->toDateString();
        $schedules = collect([$selectedEmployee, $otherEmployee])->map(function (User $employee) use ($date) {
            $schedule = FixObjectSchedule::factory()->forDate($date)->create();
            FixObjectAssignment::create([
                'fix_object_id' => $schedule->fix_object_id,
                'user_id' => $employee->id,
                'assigned_from' => now()->subDay()->toDateString(),
            ]);

            return $schedule;
        });

        $allEvents = $this->getJson(route('admin.calendar.events', [
            'from' => $date,
            'to' => $date,
            'layers' => 'fix',
        ]))->assertOk()->json('events');

        $this->assertEqualsCanonicalizing(
            $schedules->map(fn (FixObjectSchedule $schedule) => "fix_schedule:{$schedule->id}")->all(),
            collect($allEvents)->pluck('id')->all()
        );

        $filteredEvents = $this->getJson(route('admin.calendar.events', [
            'from' => $date,
            'to' => $date,
            'layers' => 'fix',
            'employee_filter' => 1,
            'employees' => [$selectedEmployee->id],
        ]))->assertOk()->json('events');

        $this->assertSame(["fix_schedule:{$schedules[0]->id}"], collect($filteredEvents)->pluck('id')->all());
    }

    public function test_calendar_fix_event_includes_site_access_and_location_details(): void
    {
        $customer = Customer::factory()->create();
        $location = CustomerLocation::factory()->create([
            'customer_id' => $customer->id,
            'street' => 'Main Street',
            'house_number' => '12',
            'postal_code' => '10115',
            'city' => 'Berlin',
            'latitude' => 52.520008,
            'longitude' => 13.404954,
            'access_instructions' => 'Key safe by the rear entrance',
            'security_code' => 'ALARM-123',
            'service_checklist' => ['Clean lobby'],
        ]);
        $fix = FixObject::factory()->create(['location_id' => $location->id]);
        $schedule = FixObjectSchedule::factory()->create([
            'fix_object_id' => $fix->id,
            'scheduled_date' => now()->toDateString(),
        ]);

        $response = $this->getJson(route('admin.calendar.events', [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
            'layers' => 'fix',
        ]))->assertOk();

        $event = collect($response->json('events'))->firstWhere('id', "fix_schedule:{$schedule->id}");
        $this->assertSame($location->fullAddress(), $event['metadata']['location_address']);
        $this->assertSame('Key safe by the rear entrance', $event['metadata']['access_instructions']);
        $this->assertSame('ALARM-123', $event['metadata']['security_code']);
        $this->assertSame(['Clean lobby'], $event['metadata']['service_checklist']);
    }

    public function test_admin_can_mark_calendar_shift_complete(): void
    {
        $shift = EmployeeShift::factory()->create(['status' => \App\Enums\ShiftStatusEnum::Planned]);

        $this->post(route('admin.calendar.shifts.complete', $shift))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(\App\Enums\ShiftStatusEnum::Completed, $shift->fresh()->status);
    }

    public function test_admin_shift_with_conflict_is_rejected_but_force_wins(): void
    {
        $employee = User::factory()->mitarbeiter()->create();

        // Existing fix booking 07:00–09:00
        $schedule = FixObjectSchedule::factory()->forDate(now()->addDay()->toDateString())->create(['scheduled_start' => '07:00:00', 'scheduled_end' => '09:00:00']);
        FixObjectAssignment::create(['fix_object_id' => $schedule->fix_object_id, 'user_id' => $employee->id, 'assigned_from' => now()->subDay()->toDateString()]);

        $payload = [
            'user_id'  => $employee->id,
            'title'    => 'Frühschicht',
            'start_at' => now()->addDay()->toDateString().' 08:00',
            'end_at'   => now()->addDay()->toDateString().' 12:00',
            'work_hours' => 4,
        ];

        // First attempt without force → validation error
        $this->post('/admin/calendar/shifts', $payload)
             ->assertSessionHasErrors('conflicts');

        $this->assertEquals(0, \App\Models\EmployeeShift::count());

        // Second attempt with force → allowed + notification logged
        $this->post('/admin/calendar/shifts', $payload + ['force' => '1'])
             ->assertSessionHasNoErrors()
             ->assertSessionHas('success');

        $this->assertEquals(1, \App\Models\EmployeeShift::count());
        // One notification for the blocked attempt, one for the forced override
        $this->assertEquals(2, \App\Models\AdminNotification::count());
    }
}