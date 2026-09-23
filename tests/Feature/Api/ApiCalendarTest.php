<?php

namespace Tests\Feature\Api;

use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragAssignee;
use App\Models\FixObjectAssignment;
use App\Models\FixObjectSchedule;
use App\Models\PersonalAppointment;
use App\Models\EmployeeShift;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function actingWithToken(User $user): self
    {
        $token = app(AuthService::class)->createApiToken($user)['token'];

        return $this->withToken($token);
    }

    public function test_employee_sees_own_fix_events_in_calendar(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($employee);

        $schedule = FixObjectSchedule::factory()->forDate(now()->addDay()->toDateString())->create(['scheduled_start' => '07:00:00', 'scheduled_end' => '09:00:00']);
        FixObjectAssignment::create(['fix_object_id' => $schedule->fix_object_id, 'user_id' => $employee->id, 'assigned_from' => now()->subDay()->toDateString()]);

        $from = now()->toDateString();
        $to   = now()->addDays(3)->toDateString();

        $response = $this->getJson("/api/v1/calendar?from={$from}&to={$to}")->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains("fix_schedule:{$schedule->id}", $ids->all());
    }

    public function test_employee_only_sees_their_own_personal_events_in_calendar(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $other    = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($employee);

        PersonalAppointment::create([
            'user_id'  => $other->id,
            'title'    => 'Fremd',
            'start_at' => now()->addDay()->toDateString().' 12:00:00',
            'end_at'   => now()->addDay()->toDateString().' 13:00:00',
        ]);

        $from = now()->toDateString();
        $to   = now()->addDays(3)->toDateString();

        $response = $this->getJson("/api/v1/calendar?from={$from}&to={$to}")->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains('personal_appointment', $ids->map(fn($id) => explode(':', $id)[0])->all());
    }

    public function test_employee_creates_own_personal_appointment(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($employee);

        $response = $this->postJson('/api/v1/appointments', [
            'title'    => 'Arzttermin',
            'start_at' => now()->addDays(2)->toDateString().' 09:00:00',
            'end_at'   => now()->addDays(2)->toDateString().' 10:00:00',
        ])->assertStatus(201);

        $this->assertEquals('Arzttermin', $response->json('data.title'));
        $this->assertEquals($employee->id, PersonalAppointment::firstOrFail()->user_id);
    }

    public function test_appointment_overlapping_existing_booking_returns_409(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($employee);

        EmployeeShift::factory()->create([
            'user_id'  => $employee->id,
            'start_at' => now()->addDays(2)->toDateString().' 08:00:00',
            'end_at'   => now()->addDays(2)->toDateString().' 10:00:00',
        ]);

        $response = $this->postJson('/api/v1/appointments', [
            'title'    => 'Kollidiert',
            'start_at' => now()->addDays(2)->toDateString().' 09:00:00',
            'end_at'   => now()->addDays(2)->toDateString().' 11:00:00',
        ])->assertStatus(409);

        $this->assertEquals(0, PersonalAppointment::count());
    }

    public function test_vorarbeiter_can_reassign_schedule(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $newEmp = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($leader);

        $schedule = FixObjectSchedule::factory()->forDate(now()->addDay()->toDateString())->create(['scheduled_start' => '07:00:00', 'scheduled_end' => '09:00:00']);
        FixObjectAssignment::create(['fix_object_id' => $schedule->fix_object_id, 'user_id' => User::factory()->mitarbeiter()->create()->id, 'assigned_from' => now()->subDay()->toDateString()]);

        $this->postJson("/api/v1/schedules/{$schedule->id}/reassign", ['user_id' => $newEmp->id])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $newEmp->id);
    }

    public function test_mitarbeiter_cannot_reassign_schedule(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->actingWithToken($employee);

        $schedule = FixObjectSchedule::factory()->create();

        $this->postJson("/api/v1/schedules/{$schedule->id}/reassign", ['user_id' => $employee->id])
            ->assertStatus(403);
    }

    public function test_extra_assignee_swap_returns_409_on_conflict(): void
    {
        $leader = User::factory()->vorarbeiter()->create();
        $this->actingWithToken($leader);

        $newEmp = User::factory()->mitarbeiter()->create();
        $order  = ExtraAuftrag::factory()->create(['scheduled_date' => now()->addDay()->toDateString(), 'scheduled_time_start' => null]);

        $schedule = FixObjectSchedule::factory()->forDate(now()->addDay()->toDateString())->create(['scheduled_start' => '07:00:00', 'scheduled_end' => '09:00:00']);
        FixObjectAssignment::create(['fix_object_id' => $schedule->fix_object_id, 'user_id' => $newEmp->id, 'assigned_from' => now()->subDay()->toDateString()]);

        $assignee = ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $order->id,
            'user_id'          => User::factory()->mitarbeiter()->create()->id,
            'role_in_order'    => \App\Enums\AssigneeRoleEnum::Member,
        ]);

        $this->postJson("/api/v1/extra-assignees/{$assignee->id}/reassign", ['user_id' => $newEmp->id])
            ->assertStatus(409);
    }
}