<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClearDataExceptAdminsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_clears_all_data_except_admin_accounts_and_migration_history(): void
    {
        $admin = User::factory()->administrator()->create();
        $deletedAdmin = User::factory()->administrator()->create();
        $deletedAdmin->delete();
        $employee = User::factory()->mitarbeiter()->create();
        Customer::factory()->create();
        $migrationTable = config('database.migrations.table');
        $migrationCount = DB::table($migrationTable)->count();

        $exitCode = Artisan::call('ems:clear-data', [
            '--confirm' => 'WIPE-ALL-DATA-EXCEPT-ADMINS',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('users', ['id' => $deletedAdmin->id]);
        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
        $this->assertDatabaseCount('customers', 0);
        $this->assertSame($migrationCount, DB::table($migrationTable)->count());
    }

    public function test_it_does_not_clear_data_without_the_confirmation_phrase(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = Customer::factory()->create();

        $exitCode = Artisan::call('ems:clear-data');

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_it_refuses_to_clear_data_when_no_admin_account_exists(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $customer = Customer::factory()->create();

        $exitCode = Artisan::call('ems:clear-data', [
            '--confirm' => 'WIPE-ALL-DATA-EXCEPT-ADMINS',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseHas('users', ['id' => $employee->id]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }
}
