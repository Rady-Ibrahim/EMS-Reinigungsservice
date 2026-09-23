<?php

namespace Tests\Feature\Admin;

use App\Models\FixObjectAssignment;
use App\Models\FixObjectSchedule;
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
             ->assertSee('Kalender');
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
        ]);

        $from = now()->toDateString();
        $to   = now()->addDays(3)->toDateString();

        $response = $this->getJson("/admin/calendar/events?from={$from}&to={$to}&layers=fix,personal")
            ->assertOk();

        $ids = collect($response->json('events'))->pluck('id')->all();
        $this->assertContains("fix_schedule:{$schedule->id}", $ids);
        $this->assertCount(1, collect($response->json('events'))->where('layer', 'personal'));
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