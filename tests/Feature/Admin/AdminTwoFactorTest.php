<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function enable2fa(User $user): void
    {
        $service = app(TwoFactorAuthService::class);
        $data = $service->setup($user);
        $service->confirm($user, app(Google2FA::class)->getCurrentOtp($data['secret']));
    }

    private function currentCode(User $user): string
    {
        $secret = $user->twoFactorAuth()->first()->secret;

        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_setup_page_shows_qr_code(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $this->get('/admin/two-factor')
             ->assertOk()
             ->assertSee('<svg', false);
    }

    public function test_admin_can_enable_two_factor_with_valid_code(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $this->get('/admin/two-factor'); // initializes secret

        $secret = session('2fa_secret');

        $this->post('/admin/two-factor/confirm', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
             ->assertRedirect(route('admin.two-factor.setup'))
             ->assertSessionHas('success');

        $this->assertTrue(app(TwoFactorAuthService::class)->isEnabledFor($admin->fresh()));
        $this->assertNotNull($admin->twoFactorAuth()->first()->confirmed_at);
    }

    public function test_admin_cannot_enable_with_invalid_code(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $this->get('/admin/two-factor');

        $this->post('/admin/two-factor/confirm', ['code' => '000000'])
             ->assertSessionHasErrors('code');

        $this->assertFalse(app(TwoFactorAuthService::class)->isEnabledFor($admin->fresh()));
    }

    public function test_login_without_2fa_goes_directly_to_dashboard(): void
    {
        $admin = User::factory()->administrator()->create(['password' => 'password']);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
             ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_with_2fa_requires_challenge(): void
    {
        $admin = User::factory()->administrator()->create(['password' => 'password']);
        $this->enable2fa($admin);
        $this->post('/logout'); // ensure not authenticated

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
             ->assertRedirect(route('admin.2fa.challenge'));

        $this->assertGuest();
    }

    public function test_2fa_challenge_with_valid_code_logs_in(): void
    {
        $admin = User::factory()->administrator()->create(['password' => 'password']);
        $this->enable2fa($admin);
        $this->post('/logout');

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
             ->assertRedirect(route('admin.2fa.challenge'));

        $this->get('/admin/2fa/challenge')
             ->assertOk()
             ->assertSee('Zwei-Faktor-Code');

        $this->post('/admin/2fa/challenge', ['code' => $this->currentCode($admin)])
             ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_2fa_challenge_rejects_invalid_code(): void
    {
        $admin = User::factory()->administrator()->create(['password' => 'password']);
        $this->enable2fa($admin);
        $this->post('/logout');

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/admin/2fa/challenge', ['code' => '000000'])
             ->assertSessionHasErrors('code');

        $this->assertGuest();
    }
}