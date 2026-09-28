<?php

namespace Tests\Feature\Admin;

use App\Enums\ComplaintStatusEnum;
use App\Models\Complaint;
use App\Models\CustomerLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for the Reklamationen back-office screens.
 *
 * The API is covered by {@see \Tests\Feature\Api\ComplaintTest}; these exist so
 * that a Blade or routing mistake in the admin views cannot slip through.
 */
class AdminComplaintTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders(): void
    {
        Complaint::factory()->count(2)->create();

        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/admin/complaints')
            ->assertOk()
            ->assertSee('Reklamationen');
    }

    public function test_index_renders_with_no_complaints(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/admin/complaints')->assertOk();
    }

    public function test_index_applies_status_filter(): void
    {
        Complaint::factory()->pending()->create();
        Complaint::factory()->resolved()->create();

        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/admin/complaints?status='.ComplaintStatusEnum::Pending->value)
            ->assertOk();
    }

    public function test_create_screen_renders(): void
    {
        CustomerLocation::factory()->create();

        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/admin/complaints/create')->assertOk();
    }

    public function test_show_screen_renders(): void
    {
        $complaint = Complaint::factory()->withPhotos()->create();

        $this->actingAs(User::factory()->administrator()->create());

        $this->get('/admin/complaints/'.$complaint->id)
            ->assertOk()
            ->assertSee($complaint->title);
    }

    public function test_store_creates_the_complaint(): void
    {
        $location = CustomerLocation::factory()->create();

        $this->actingAs(User::factory()->administrator()->create());

        $this->post('/admin/complaints', [
            'location_id' => $location->id,
            'title'       => 'Fenster streifig',
            'description' => 'Streifen sichtbar.',
        ])->assertRedirect();

        $this->assertDatabaseHas('complaints', ['title' => 'Fenster streifig']);
    }

    public function test_status_can_be_advanced_from_the_web(): void
    {
        $complaint = Complaint::factory()->pending()->create();

        $this->actingAs(User::factory()->administrator()->create());

        $this->patch('/admin/complaints/'.$complaint->id.'/status', [
            'status'     => ComplaintStatusEnum::InProgress->value,
            'resolution' => null,
        ])->assertRedirect();

        $this->assertSame(
            ComplaintStatusEnum::InProgress->value,
            $complaint->fresh()->status->value
        );
    }

    public function test_resolving_from_the_web_requires_a_resolution(): void
    {
        $complaint = Complaint::factory()->pending()->create();

        $this->actingAs(User::factory()->administrator()->create());

        $this->patch('/admin/complaints/'.$complaint->id.'/status', [
            'status'     => ComplaintStatusEnum::Resolved->value,
            'resolution' => '',
        ])->assertSessionHasErrors('resolution');

        $this->assertSame(
            ComplaintStatusEnum::Pending->value,
            $complaint->fresh()->status->value
        );
    }

    public function test_can_assign_a_complaint(): void
    {
        $complaint = Complaint::factory()->create(['assigned_employee_id' => null]);
        $employee  = User::factory()->mitarbeiter()->create();

        $this->actingAs(User::factory()->administrator()->create());

        $this->patch('/admin/complaints/'.$complaint->id.'/assign', [
            'assigned_employee_id' => $employee->id,
        ])->assertRedirect();

        $this->assertSame($employee->id, $complaint->fresh()->assigned_employee_id);
    }

    public function test_web_area_is_closed_to_mitarbeiter(): void
    {
        $this->actingAs(User::factory()->mitarbeiter()->create());

        $this->get('/admin/complaints')->assertForbidden();
    }
}
