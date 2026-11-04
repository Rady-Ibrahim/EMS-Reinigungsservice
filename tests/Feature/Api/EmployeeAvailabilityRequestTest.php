<?php

namespace Tests\Feature\Api;

use App\Enums\EmployeeAvailabilityStatusEnum;
use App\Models\EmployeeAvailabilityRequest;
use App\Models\EmployeeShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeAvailabilityRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_submit_and_list_only_their_own_availability_requests(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $otherEmployee = User::factory()->mitarbeiter()->create();
        Sanctum::actingAs($employee);

        $start = now()->addDays(3)->setTime(8, 0);
        $response = $this->postJson('/api/v1/availability-requests', [
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->copy()->addHours(4)->toIso8601String(),
            'address' => 'Main Street 12',
            'notes' => 'Please assign this shift.',
        ])->assertCreated()
            ->assertJsonPath('data.status', EmployeeAvailabilityStatusEnum::Pending->value)
            ->assertJsonPath('data.address', 'Main Street 12');

        $otherRequest = EmployeeAvailabilityRequest::create([
            'user_id' => $otherEmployee->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
            'address' => 'Other address',
        ]);

        $this->getJson('/api/v1/availability-requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $response->json('data.id'))
            ->assertJsonMissing(['id' => $otherRequest->id]);
    }

    public function test_administrator_cannot_submit_an_employee_availability_request_from_mobile(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());

        $start = now()->addDay()->setTime(8, 0);
        $this->postJson('/api/v1/availability-requests', [
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->copy()->addHour()->toIso8601String(),
            'address' => 'Main Street 12',
        ])->assertForbidden();

        $this->assertDatabaseCount('employee_availability_requests', 0);
    }

    public function test_employee_request_requires_an_address_and_a_valid_work_period(): void
    {
        Sanctum::actingAs(User::factory()->mitarbeiter()->create());

        $start = now()->addDays(3)->setTime(8, 0);
        $this->postJson('/api/v1/availability-requests', [
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->copy()->addMinutes(10)->toIso8601String(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['address', 'end_at']);

        $this->assertDatabaseCount('employee_availability_requests', 0);
    }

    public function test_admin_approval_creates_a_calendar_shift_and_preserves_the_request(): void
    {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->mitarbeiter()->create();
        $start = now()->addDays(3)->setTime(8, 0);
        $availability = EmployeeAvailabilityRequest::create([
            'user_id' => $employee->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(4),
            'address' => 'Main Street 12',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.availability-requests.approve', $availability))
            ->assertRedirect();

        $availability->refresh();
        $this->assertSame(EmployeeAvailabilityStatusEnum::Approved, $availability->status);
        $this->assertNotNull($availability->employee_shift_id);
        $this->assertDatabaseHas('employee_shifts', [
            'id' => $availability->employee_shift_id,
            'user_id' => $employee->id,
            'notes' => 'Main Street 12',
            'status' => 'planned',
        ]);

        $this->getJson(route('admin.calendar.events', [
            'from' => $start->toDateString(),
            'to' => $start->toDateString(),
            'layers' => 'shifts',
        ]))->assertOk()
            ->assertJsonFragment(['id' => 'employee_shift:'.$availability->employee_shift_id])
            ->assertJsonFragment(['location' => 'Main Street 12']);
    }

    public function test_request_conflict_leaves_it_pending_and_does_not_create_a_shift(): void
    {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->mitarbeiter()->create();
        $start = now()->addDays(3)->setTime(8, 0);
        EmployeeShift::create([
            'user_id' => $employee->id,
            'created_by' => $admin->id,
            'title' => 'Existing shift',
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
            'status' => 'planned',
        ]);
        $availability = EmployeeAvailabilityRequest::create([
            'user_id' => $employee->id,
            'start_at' => $start->copy()->addHour(),
            'end_at' => $start->copy()->addHours(3),
            'address' => 'Main Street 12',
        ]);
        $shiftCount = EmployeeShift::count();

        $this->actingAs($admin)
            ->from(route('admin.availability-requests.index'))
            ->post(route('admin.availability-requests.approve', $availability))
            ->assertRedirect(route('admin.availability-requests.index'))
            ->assertSessionHasErrors();

        $this->assertSame(EmployeeAvailabilityStatusEnum::Pending, $availability->fresh()->status);
        $this->assertSame($shiftCount, EmployeeShift::count());
    }

    public function test_admin_can_reject_a_request_with_a_note_and_see_it_in_the_request_page(): void
    {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->mitarbeiter()->create(['name' => 'Availability Employee']);
        $start = now()->addDays(3)->setTime(8, 0);
        $availability = EmployeeAvailabilityRequest::create([
            'user_id' => $employee->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(4),
            'address' => 'Main Street 12',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.availability-requests.reject', $availability), [
                'admin_note' => 'The schedule is already full.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('employee_availability_requests', [
            'id' => $availability->id,
            'status' => EmployeeAvailabilityStatusEnum::Rejected->value,
            'admin_note' => 'The schedule is already full.',
        ]);

        $this->get(route('admin.availability-requests.index'))
            ->assertOk()
            ->assertSee('Availability Employee')
            ->assertSee('The schedule is already full.')
            ->assertSee(__('company.nav_section'))
            ->assertSee(__('company.company'));
    }
}
