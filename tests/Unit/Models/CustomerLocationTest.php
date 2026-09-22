<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\CustomerLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_has_many_locations(): void
    {
        $customer = Customer::factory()->create();
        CustomerLocation::factory()->count(3)->create(['customer_id' => $customer->id]);

        $this->assertCount(3, $customer->fresh()->locations);
    }

    public function test_location_belongs_to_customer(): void
    {
        $customer = Customer::factory()->create();
        $location = CustomerLocation::factory()->create(['customer_id' => $customer->id]);

        $this->assertEquals($customer->id, $location->customer->id);
    }

    public function test_security_code_is_encrypted_in_database(): void
    {
        $location = CustomerLocation::factory()->withSecurityCode()->create();
        $rawValue = \Illuminate\Support\Facades\DB::table('customer_locations')
                        ->where('id', $location->id)
                        ->value('security_code');

        $this->assertNotEquals($location->security_code, $rawValue, 'security_code must be encrypted');
        $this->assertNotNull($location->fresh()->security_code, 'Decrypted value should be readable');
    }

    public function test_full_address_helper_formats_correctly(): void
    {
        $location = CustomerLocation::factory()->make([
            'street'       => 'Musterstraße',
            'house_number' => '12',
            'postal_code'  => '80331',
            'city'         => 'München',
        ]);

        $this->assertEquals('Musterstraße 12, 80331 München', $location->fullAddress());
    }

    public function test_active_scope_excludes_inactive_locations(): void
    {
        CustomerLocation::factory()->count(2)->create(['is_active' => true]);
        CustomerLocation::factory()->count(1)->create(['is_active' => false]);

        $this->assertCount(2, CustomerLocation::active()->get());
    }

    public function test_working_days_stored_as_json_array(): void
    {
        $location = CustomerLocation::factory()->create([
            'working_days' => ['Mon', 'Wed', 'Fri'],
        ]);

        $retrieved = $location->fresh()->working_days;
        $this->assertIsArray($retrieved);
        $this->assertContains('Mon', $retrieved);
        $this->assertCount(3, $retrieved);
    }
}
