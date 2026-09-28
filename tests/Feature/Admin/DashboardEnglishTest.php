<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\CustomerLocation;
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
}
