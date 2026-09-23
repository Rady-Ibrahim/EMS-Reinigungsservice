<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TwoFactorAuthService::class);
    }

    private function validCode(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_setup_generates_secret_and_qr(): void
    {
        $user = User::factory()->administrator()->create();

        $data = $this->service->setup($user);

        $this->assertArrayHasKey('secret', $data);
        $this->assertArrayHasKey('qr_svg', $data);
        $this->assertStringContainsStringIgnoringCase('<svg', $data['qr_svg']);
        $this->assertFalse($this->service->isEnabledFor($user));
    }

    public function test_confirm_with_invalid_code_fails(): void
    {
        $user = User::factory()->administrator()->create();
        $this->service->setup($user);

        $this->expectException(\RuntimeException::class);
        $this->service->confirm($user, '000000');
    }

    public function test_confirm_with_valid_code_enables_and_returns_recovery_codes(): void
    {
        $user = User::factory()->administrator()->create();
        $data = $this->service->setup($user);

        $result = $this->service->confirm($user, $this->validCode($data['secret']));

        $this->assertTrue($this->service->isEnabledFor($user));
        $this->assertCount(10, $result['recovery_codes']);

        $row = $user->twoFactorAuth()->first();
        $this->assertNotNull($row->confirmed_at);
        $this->assertCount(10, $row->recovery_codes);
    }

    public function test_challenge_accepts_totp_and_recovery_code(): void
    {
        $user = User::factory()->administrator()->create();
        $data = $this->service->setup($user);
        $result = $this->service->confirm($user, $this->validCode($data['secret']));

        // TOTP
        $this->assertTrue($this->service->challenge($user, $this->validCode($data['secret'])));
        // Recovery code (consumed once)
        $first = $result['recovery_codes'][0];
        $this->assertTrue($this->service->challenge($user, $first));
        $this->assertEquals(9, $this->service->remainingRecoveryCodes($user));
        // Reusing a consumed recovery code fails
        $this->assertFalse($this->service->challenge($user, $first));
        $this->assertEquals(9, $this->service->remainingRecoveryCodes($user));
    }

    public function test_disable_requires_password(): void
    {
        $user = User::factory()->administrator()->create();
        $data = $this->service->setup($user);
        $this->service->confirm($user, $this->validCode($data['secret']));

        $this->expectException(\RuntimeException::class);
        $this->service->disable($user, 'wrong-password');
    }

    public function test_disable_with_password_deactivates(): void
    {
        $user = User::factory()->administrator()->create();
        $data = $this->service->setup($user);
        $this->service->confirm($user, $this->validCode($data['secret']));

        $this->service->disable($user, 'password');

        $this->assertFalse($this->service->isEnabledFor($user));
        $this->assertNull($user->twoFactorAuth()->first()->confirmed_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event'   => 'security_changed',
        ]);
    }
}