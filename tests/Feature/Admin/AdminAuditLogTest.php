<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->administrator()->create());
    }

    public function test_admin_can_view_audit_log_index(): void
    {
        $this->get('/admin/audit-log')
             ->assertOk()
             ->assertSee('Audit-Protokoll');
    }

    public function test_admin_can_see_created_customer_in_audit_log(): void
    {
        Customer::create([
            'name'           => 'Ziel GmbH',
            'contact_person' => 'A',
            'email'          => 'ziel@example.de',
            'phone'          => '1',
        ]);

        $this->get('/admin/audit-log')
             ->assertOk()
             ->assertSee('Ziel GmbH')
             ->assertSee('Customer');
    }

    public function test_admin_can_filter_audit_log_by_event(): void
    {
        Customer::create([
            'name'           => 'Test GmbH',
            'contact_person' => 'A',
            'email'          => 't@example.de',
            'phone'          => '1',
        ]);

        $this->get('/admin/audit-log?event=created')
             ->assertOk()
             ->assertSee('Test GmbH');

        $this->get('/admin/audit-log?event=deleted')
             ->assertOk();
    }

    public function test_admin_can_view_audit_entry_details(): void
    {
        $customer = Customer::create([
            'name'           => 'Detail GmbH',
            'contact_person' => 'A',
            'email'          => 'd@example.de',
            'phone'          => '1',
        ]);

        $log = $customer->auditLogs()->latest('id')->first();

        $this->assertNotNull($log);

        $this->get("/admin/audit-log/{$log->id}")
             ->assertOk()
             ->assertSee('Detail GmbH');
    }
}