<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleEnum;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->administrator()->create();
    }

    // ── Observer: Auto-create EmployeeProfile ──────────────────────────────

    public function test_employee_profile_is_auto_created_for_mitarbeiter(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $this->assertDatabaseHas('employee_profiles', ['user_id' => $user->id]);
    }

    public function test_employee_profile_is_auto_created_for_vorarbeiter(): void
    {
        $user = User::factory()->vorarbeiter()->create();
        $this->assertDatabaseHas('employee_profiles', ['user_id' => $user->id]);
    }

    public function test_employee_profile_is_NOT_created_for_administrator(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->assertDatabaseMissing('employee_profiles', ['user_id' => $admin->id]);
    }

    public function test_each_employee_gets_unique_calendar_color(): void
    {
        $users = User::factory()->mitarbeiter()->count(5)->create();
        $colors = EmployeeProfile::whereIn('user_id', $users->pluck('id'))->pluck('calendar_color');
        $this->assertEquals($colors->count(), $colors->unique()->count(), 'All calendar colors should be unique');
    }

    // ── Admin CRUD ─────────────────────────────────────────────────────────

    public function test_admin_can_create_employee(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.employees.store'), [
            'name'          => 'Max Mustermann',
            'email'         => 'max@ems.de',
            'password'      => 'Password1',
            'role'          => 'mitarbeiter',
            'locale'        => 'de',
            'contract_type' => 'minijob',
        ]);

        $response->assertRedirect(route('admin.employees.index'));
        $this->assertDatabaseHas('users', ['email' => 'max@ems.de', 'role' => 'mitarbeiter']);
    }

    public function test_admin_can_update_employee(): void
    {
        $employee = User::factory()->mitarbeiter()->create();

        $this->actingAs($this->admin)->put(route('admin.employees.update', $employee), [
            'name'   => 'Updated Name',
            'email'  => $employee->email,
            'role'   => 'mitarbeiter',
            'locale' => 'ar',
        ]);

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'name' => 'Updated Name', 'locale' => 'ar']);
    }

    public function test_admin_can_soft_delete_employee(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $this->actingAs($this->admin)->delete(route('admin.employees.destroy', $employee));
        $this->assertSoftDeleted('users', ['id' => $employee->id]);
    }

    public function test_admin_cannot_delete_another_admin(): void
    {
        $otherAdmin = User::factory()->administrator()->create();
        $response = $this->actingAs($this->admin)->delete(route('admin.employees.destroy', $otherAdmin));
        $response->assertNotFound();
    }

    // ── Data Isolation: Financial fields ──────────────────────────────────

    public function test_hourly_rate_is_encrypted_in_database(): void
    {
        $user = User::factory()->mitarbeiter()->create();
        $user->employeeProfile->update(['hourly_rate' => '14.50']);

        // Raw DB value should NOT equal the plaintext rate
        $raw = \Illuminate\Support\Facades\DB::table('employee_profiles')
                    ->where('id', $user->employeeProfile->id)
                    ->value('hourly_rate');

        $this->assertNotEquals('14.50', $raw, 'hourly_rate must be encrypted in the DB');
        $this->assertEquals('14.50', $user->employeeProfile->fresh()->hourly_rate);
    }

    public function test_iban_is_encrypted_in_database(): void
    {
        $iban = 'DE89370400440532013000';
        $user = User::factory()->mitarbeiter()->create();
        $user->employeeProfile->update(['iban' => $iban]);

        $raw = \Illuminate\Support\Facades\DB::table('employee_profiles')
                    ->where('id', $user->employeeProfile->id)
                    ->value('iban');

        $this->assertNotEquals($iban, $raw, 'IBAN must be encrypted in the DB');
        $this->assertEquals($iban, $user->employeeProfile->fresh()->iban);
    }
}
