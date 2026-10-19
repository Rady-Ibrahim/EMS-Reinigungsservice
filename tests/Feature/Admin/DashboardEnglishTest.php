<?php

namespace Tests\Feature\Admin;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExecutionStatusEnum;
use App\Enums\ExtraExecutionStatusEnum;
use App\Models\Complaint;
use App\Models\Company;
use App\Models\CustomerLocation;
use App\Models\ExtraAuftrag;
use App\Models\ExtraAuftragAssignee;
use App\Models\ExtraAuftragExecution;
use App\Models\FixObject;
use App\Models\FixObjectAssignment;
use App\Models\FixObjectExecution;
use App\Models\FixObjectSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the English rendering of the dashboard and the shared admin chrome.
 *
 * `lang/en/messages.php` is the only group these screens need, so a regression
 * there would silently show German to an English user. These assertions fail
 * loudly if that happens.
 */
class DashboardEnglishTest extends TestCase
{
    use RefreshDatabase;

    private function asEnglishAdmin(): User
    {
        $admin = User::factory()->administrator()->create([
            'locale' => 'en',
        ]);

        $this->actingAs($admin);

        return $admin;
    }

    public function test_dashboard_renders_in_english(): void
    {
        CustomerLocation::factory()->create();

        $this->asEnglishAdmin();

        $response = $this->get('/admin/dashboard')->assertOk();

        $body = $response->getContent();

        // Labels that only exist as English strings.
        $this->assertStringContainsString('Customers', $body);
        $this->assertStringContainsString('Employees', $body);
        $this->assertStringContainsString('Fix Objects', $body);
        $this->assertStringContainsString('Go to calendar', $body);
        $this->assertStringContainsString('Complaints &amp; Notifications', $body);

        // The German originals must be gone.
        $this->assertStringNotContainsString('Kunden', $body);
        $this->assertStringNotContainsString('Mitarbeiter', $body);
        $this->assertStringNotContainsString('Reklamationen', $body);
        $this->assertStringNotContainsString('Zum Kalender', $body);
    }

    public function test_admin_chrome_nav_is_in_english(): void
    {
        $this->asEnglishAdmin();

        $body = $this->get('/admin/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Overview', $body);
        $this->assertStringContainsString('Master Data', $body);
        $this->assertStringContainsString('Complaints', $body);
        $this->assertStringContainsString('Documentation', $body);
        $this->assertStringNotContainsString('Stammdaten', $body);
        $this->assertStringNotContainsString('Übersicht', $body);
    }

    public function test_complaints_nav_link_points_at_the_complaints_module(): void
    {
        Complaint::factory()->count(2)->pending()->create();
        Complaint::factory()->resolved()->create();

        $this->asEnglishAdmin();

        $body = $this->get('/admin/dashboard')->assertOk()->getContent();

        // The link must target the complaints screen, not the notification inbox.
        $this->assertStringContainsString('/admin/complaints', $body);

        // Only the two open ones are badged.
        $this->assertMatchesRegularExpression(
            '/nav-badge[^>]*>\s*2\s*</',
            $body,
            'Expected a red nav badge showing the 2 pending complaints.'
        );
    }

    public function test_no_badge_when_nothing_is_pending(): void
    {
        Complaint::factory()->resolved()->create();

        $this->asEnglishAdmin();

        $body = $this->get('/admin/dashboard')->assertOk()->getContent();

        // The link stays, it simply carries no counter.
        $this->assertStringContainsString('/admin/complaints', $body);
        $this->assertDoesNotMatchRegularExpression(
            '/nav-badge[^>]*>\s*0\s*</',
            $body,
            'A badge showing 0 pending complaints should not be rendered.'
        );
    }

    public function test_dashboard_shows_detailed_operations_and_admin_financial_summary(): void
    {
        $this->asEnglishAdmin();
        $company = Company::factory()->create(['name' => 'Bright Cleaning']);
        $supervisor = User::factory()->vorarbeiter()->create([
            'name' => 'Mira Supervisor',
            'company_id' => $company->id,
        ]);

        $fixObject = FixObject::factory()->create([
            'title' => 'Office Contract',
            'company_id' => $company->id,
            'price_per_month' => '500',
            'company_payout' => '100',
        ]);
        $fixObject->location->update([
            'name' => 'Berlin HQ',
            'street' => 'Main Street',
            'house_number' => '10',
            'postal_code' => '10115',
            'city' => 'Berlin',
            'latitude' => '52.52000000',
            'longitude' => '13.40500000',
        ]);
        FixObjectAssignment::create([
            'fix_object_id' => $fixObject->id,
            'user_id' => $supervisor->id,
            'assigned_from' => now()->subDay()->toDateString(),
        ]);
        $schedule = FixObjectSchedule::factory()->forDate(now()->toDateString())->create([
            'fix_object_id' => $fixObject->id,
        ]);
        FixObjectExecution::create([
            'schedule_id' => $schedule->id,
            'user_id' => $supervisor->id,
            'actual_start' => now()->subHour(),
            'actual_end' => now(),
            'status' => ExecutionStatusEnum::Completed,
            'before_photos' => ['before.jpg', 'before-2.jpg'],
            'after_photos' => ['after.jpg'],
            'contract_hours_applied' => 0,
        ]);

        $extraOrder = ExtraAuftrag::factory()->create([
            'title' => 'Window Cleaning',
            'scheduled_date' => now()->toDateString(),
            'company_id' => $company->id,
            'price' => '200',
            'company_payout' => '50',
            'has_key_safe' => true,
            'has_alarm_system' => true,
        ]);
        ExtraAuftragAssignee::create([
            'extra_auftrag_id' => $extraOrder->id,
            'user_id' => $supervisor->id,
            'role_in_order' => AssigneeRoleEnum::Leader,
        ]);
        ExtraAuftragExecution::create([
            'extra_auftrag_id' => $extraOrder->id,
            'user_id' => $supervisor->id,
            'work_start' => now()->subHour(),
            'work_end' => now(),
            'status' => ExtraExecutionStatusEnum::Completed,
            'before_photos' => ['before.jpg'],
            'after_photos' => ['after.jpg'],
            'checklist_items' => [
                ['task' => 'Floors', 'completed' => true],
                ['task' => 'Windows', 'completed' => false],
            ],
        ]);

        $body = $this->get('/admin/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Office Contract', $body);
        $this->assertStringContainsString('Berlin HQ', $body);
        $this->assertStringContainsString('Main Street 10, 10115 Berlin', $body);
        $this->assertStringContainsString('52.52000000, 13.40500000', $body);
        $this->assertStringContainsString('Window Cleaning', $body);
        $this->assertStringContainsString('1/2 completed', $body);
        $this->assertStringContainsString('Key safe', $body);
        $this->assertStringContainsString('Alarm configured', $body);
        $this->assertStringContainsString('700,00', $body);
        $this->assertStringContainsString('150,00', $body);
        $this->assertStringContainsString('Administrator only', $body);
        $this->assertStringContainsString('Bright Cleaning', $body);
    }

    public function test_dashboard_financial_data_is_not_available_to_employees(): void
    {
        $this->actingAs(User::factory()->mitarbeiter()->create());

        $this->get('/admin/dashboard')->assertForbidden();
    }
}
