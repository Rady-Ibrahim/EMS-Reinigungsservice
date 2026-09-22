<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerStatusEnum;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->administrator()->create();
    }

    // ── Index ──────────────────────────────────────────────────────────────

    public function test_admin_can_view_customers_list(): void
    {
        Customer::factory()->count(3)->create();
        $response = $this->actingAs($this->admin)->get(route('admin.customers.index'));
        $response->assertStatus(200)->assertViewIs('admin.customers.index');
    }

    public function test_guest_cannot_access_customers(): void
    {
        $response = $this->get(route('admin.customers.index'));
        $response->assertRedirect(route('login'));
    }

    // ── Create / Store ─────────────────────────────────────────────────────

    public function test_admin_can_create_a_customer(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.customers.store'), [
            'name'    => 'Muster GmbH',
            'email'   => 'info@muster.de',
            'phone'   => '+49123456789',
            'status'  => 'active',
        ]);

        $response->assertRedirect(route('admin.customers.index'));
        $this->assertDatabaseHas('customers', ['name' => 'Muster GmbH', 'email' => 'info@muster.de']);
    }

    public function test_customer_creation_requires_name(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.customers.store'), [
            'status' => 'active',
        ]);
        $response->assertSessionHasErrors('name');
    }

    // ── Update ─────────────────────────────────────────────────────────────

    public function test_admin_can_update_a_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin)->put(route('admin.customers.update', $customer), [
            'name'   => 'New Name',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'New Name']);
    }

    // ── Toggle Status ──────────────────────────────────────────────────────

    public function test_admin_can_toggle_customer_status(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatusEnum::Active]);

        $this->actingAs($this->admin)->patch(route('admin.customers.toggle-status', $customer));

        $this->assertDatabaseHas('customers', [
            'id'     => $customer->id,
            'status' => CustomerStatusEnum::Inactive->value,
        ]);
    }

    // ── Soft Delete ────────────────────────────────────────────────────────

    public function test_admin_can_soft_delete_a_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)->delete(route('admin.customers.destroy', $customer));

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_deleted_customer_is_not_visible_in_list(): void
    {
        $customer = Customer::factory()->create(['name' => 'Deleted Co']);
        $customer->delete();

        $response = $this->actingAs($this->admin)->get(route('admin.customers.index'));
        $response->assertDontSee('Deleted Co');
    }

    // ── Audit Log ──────────────────────────────────────────────────────────

    public function test_audit_log_is_created_on_customer_creation(): void
    {
        $this->actingAs($this->admin)->post(route('admin.customers.store'), [
            'name'   => 'Audit Test GmbH',
            'status' => 'active',
        ]);

        $customer = Customer::where('name', 'Audit Test GmbH')->first();
        $this->assertNotNull($customer);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Customer::class,
            'auditable_id'   => $customer->id,
            'event'          => 'created',
        ]);
    }

    public function test_audit_log_records_changes_on_update(): void
    {
        $customer = Customer::factory()->create(['name' => 'Before']);

        $this->actingAs($this->admin)->put(route('admin.customers.update', $customer), [
            'name'   => 'After',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Customer::class,
            'auditable_id'   => $customer->id,
            'event'          => 'updated',
        ]);
    }
}
