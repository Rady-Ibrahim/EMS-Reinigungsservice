<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────
    // Admin Web Login
    // ──────────────────────────────────────────────────────────────────────

    public function test_admin_login_page_is_accessible(): void
    {
        $response = $this->get(route('admin.login'));
        $response->assertStatus(200);
        $response->assertViewIs('admin.auth.login');
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = User::factory()->administrator()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->post(route('admin.login.post'), [
            'email'    => $admin->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_login_fails_with_wrong_password(): void
    {
        $admin = User::factory()->administrator()->create([
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->post(route('admin.login.post'), [
            'email'    => $admin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_login_fails_for_inactive_account(): void
    {
        $admin = User::factory()->administrator()->inactive()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->post(route('admin.login.post'), [
            'email'    => $admin->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_vorarbeiter_cannot_login_via_web(): void
    {
        $vorarbeiter = User::factory()->vorarbeiter()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->post(route('admin.login.post'), [
            'email'    => $vorarbeiter->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_mitarbeiter_cannot_login_via_web(): void
    {
        $mitarbeiter = User::factory()->mitarbeiter()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->post(route('admin.login.post'), [
            'email'    => $mitarbeiter->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_dashboard_is_inaccessible_to_guests(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_logout(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $response = $this->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_login_form_validation_requires_email(): void
    {
        $response = $this->post(route('admin.login.post'), [
            'email'    => '',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_login_form_validation_requires_valid_email_format(): void
    {
        $response = $this->post(route('admin.login.post'), [
            'email'    => 'not-an-email',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
