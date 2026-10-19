<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\ExtraAuftrag;
use App\Models\FixObject;
use App\Models\FixObjectAssignment;
use App\Models\FixObjectSchedule;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->administrator()->create();
    }

    // ── Admin CRUD ─────────────────────────────────────────────────────────

    public function test_admin_can_view_companies_index(): void
    {
        Company::factory()->create(['name' => 'Blitz Blank GmbH']);

        $this->actingAs($this->admin)
            ->get(route('admin.companies.index'))
            ->assertOk()
            ->assertSee('Blitz Blank GmbH');
    }

    public function test_admin_can_create_company(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.companies.store'), [
                'name' => 'Glanz AG',
                'phone' => '0170 1234567',
                'notes' => 'Nur Wochenende',
            ])
            ->assertRedirect(route('admin.companies.index'));

        $this->assertDatabaseHas('companies', ['name' => 'Glanz AG', 'phone' => '0170 1234567']);
    }

    public function test_company_name_is_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.companies.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_company_name_must_be_unique(): void
    {
        Company::factory()->create(['name' => 'Glanz AG']);

        $this->actingAs($this->admin)
            ->post(route('admin.companies.store'), ['name' => 'Glanz AG'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_company(): void
    {
        $company = Company::factory()->create(['name' => 'Alt GmbH']);

        $this->actingAs($this->admin)
            ->put(route('admin.companies.update', $company), ['name' => 'Neu GmbH'])
            ->assertRedirect(route('admin.companies.index'));

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'Neu GmbH']);
    }

    public function test_admin_can_soft_delete_company(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.companies.destroy', $company))
            ->assertRedirect(route('admin.companies.index'));

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }

    // ── Employee linkage ───────────────────────────────────────────────────

    public function test_employee_can_be_assigned_to_a_company(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.employees.store'), [
                'name' => 'Mona Beispiel',
                'email' => 'mona@ems.de',
                'password' => 'Password1',
                'role' => 'mitarbeiter',
                'locale' => 'de',
                'company_id' => $company->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'mona@ems.de', 'company_id' => $company->id]);
    }

    public function test_company_relationship_lists_its_employees(): void
    {
        $company = Company::factory()->create();
        User::factory()->mitarbeiter()->create(['company_id' => $company->id]);
        User::factory()->mitarbeiter()->create();

        $this->assertCount(1, $company->users);
    }

    // ── company_payout is encrypted at rest ────────────────────────────────

    public function test_fix_object_company_payout_is_encrypted_at_rest(): void
    {
        $company = Company::factory()->create();
        $fixObject = FixObject::factory()->create([
            'company_id' => $company->id,
            'company_payout' => '250.50',
        ]);

        $raw = DB::table('fix_objects')->where('id', $fixObject->id)->value('company_payout');

        $this->assertNotEquals('250.50', $raw, 'company_payout must be encrypted in the DB');
        $this->assertEquals('250.50', $fixObject->fresh()->company_payout);
    }

    public function test_extra_order_company_payout_is_encrypted_at_rest(): void
    {
        $company = Company::factory()->create();
        $order = ExtraAuftrag::factory()->create([
            'company_id' => $company->id,
            'company_payout' => '99.90',
        ]);

        $raw = DB::table('extra_auftraege')->where('id', $order->id)->value('company_payout');

        $this->assertNotEquals('99.90', $raw, 'company_payout must be encrypted in the DB');
        $this->assertEquals('99.90', $order->fresh()->company_payout);
    }

    // ── API must never leak company payout ─────────────────────────────────

    public function test_calendar_api_does_not_leak_company_payout(): void
    {
        $employee = User::factory()->mitarbeiter()->create();
        $company = Company::factory()->create(['name' => 'Geheim GmbH']);

        $schedule = FixObjectSchedule::factory()
            ->forDate(now()->addDay()->toDateString())
            ->create(['scheduled_start' => '07:00:00', 'scheduled_end' => '09:00:00']);

        $schedule->fixObject->update([
            'company_id' => $company->id,
            'company_payout' => '123.45',
        ]);

        FixObjectAssignment::create([
            'fix_object_id' => $schedule->fix_object_id,
            'user_id' => $employee->id,
            'assigned_from' => now()->subDay()->toDateString(),
        ]);

        $token = app(AuthService::class)->createApiToken($employee)['token'];

        $from = now()->toDateString();
        $to = now()->addDays(3)->toDateString();

        $response = $this->withToken($token)
            ->getJson("/api/v1/calendar?from={$from}&to={$to}")
            ->assertOk();

        $response->assertDontSee('123.45');
        $response->assertDontSee('Geheim GmbH');

        $event = collect($response->json('data'))
            ->firstWhere('id', "fix_schedule:{$schedule->id}");

        $this->assertNotNull($event);
        $this->assertArrayNotHasKey('company_payout', $event['metadata'] ?? []);
        $this->assertArrayNotHasKey('company_name', $event['metadata'] ?? []);
    }
}
