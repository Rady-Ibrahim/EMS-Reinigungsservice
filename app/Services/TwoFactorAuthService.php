<?php

namespace App\Services;

use App\Enums\AuditEventEnum;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor authentication for Administrator accounts (web).
 * API (mobile) sessions are not affected.
 *
 * Flow:
 *  1. setup()      — generate secret + QR code, NOT yet confirmed.
 *  2. confirm()    — verify a code, persist secret + hashed recovery codes.
 *  3. challenge()  — verify a login code / consume recovery code.
 *  4. disable()    — requires the current password; keeps the row as archive.
 *
 * Recovery codes are stored as SHA-256 hashes and shown only once.
 */
class TwoFactorAuthService
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AuditLogger $audit
    ) {
    }

    // ── Setup ──────────────────────────────────────────────────────────────

    /**
     * Start 2FA setup. Throws if already enabled.
     *
     * @return array{secret: string, provisioning_uri: string, qr_svg: string}
     */
    public function setup(User $user): array
    {
        if ($user->twoFactorAuth?->isEnabled()) {
            throw new \RuntimeException('Zwei-Faktor-Authentifizierung ist bereits aktiv.');
        }

        $secret = $this->google2fa->generateSecretKey(32);

        $row = $user->twoFactorAuth()
            ->firstOrCreate(['user_id' => $user->id], ['secret' => $secret]);

        if ($row->confirmed_at !== null) {
            throw new \RuntimeException('Zwei-Faktor-Authentifizierung ist bereits aktiv.');
        }

        $row->update(['secret' => $secret]);

        $uri = $this->google2fa->getQRCodeUrl(
            config('app.name', 'EMS'),
            $user->email,
            $secret
        );

        return [
            'secret'          => $secret,
            'provisioning_uri'=> $uri,
            'qr_svg'          => $this->qrSvg($uri),
        ];
    }

    /**
     * Confirm setup after verifying a current auth code.
     * Generates recovery codes here and returns them in plaintext ONLY once.
     */
    public function confirm(User $user, string $code): array
    {
        $row = $user->twoFactorAuth()->first();

        if (! $row || $row->confirmed_at !== null) {
            throw new \RuntimeException('Setup wurde noch nicht gestartet oder ist bereits aktiv.');
        }

        if (! $this->verifyTotp($row->secret, $code)) {
            throw new \RuntimeException('Der Code ist ungültig.');
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $row->update([
            'secret'          => $row->secret,
            'recovery_codes'  => $this->hashRecoveryCodes($recoveryCodes),
            'confirmed_at'    => now(),
            'last_used_at'    => null,
        ]);

        $this->audit->record($user, AuditEventEnum::SecurityChanged,
            oldValues: ['two_factor_enabled' => false],
            newValues: ['two_factor_enabled' => true],
            reason: '2FA aktiviert',
            actorId: $user->id
        );

        return [
            'recovery_codes' => $recoveryCodes,
        ];
    }

    // ── Login challenge ────────────────────────────────────────────────────

    public function isEnabledFor(User $user): bool
    {
        return $user->twoFactorAuth()->first()?->isEnabled() ?? false;
    }

    /**
     * Verify a login TOTP code or consume a recovery code.
     * Updates last_used_at on success.
     */
    public function challenge(User $user, string $input): bool
    {
        $row = $user->twoFactorAuth()->first();

        if (! $row?->isEnabled()) {
            return false;
        }

        if ($this->consumeRecoveryCode($row, $input)) {
            $row->update(['last_used_at' => now()]);
            return true;
        }

        if ($this->verifyTotp($row->secret, $input)) {
            $row->update(['last_used_at' => now()]);
            return true;
        }

        return false;
    }

    /**
     * Remaining usable recovery codes (used to inform the admin).
     */
    public function remainingRecoveryCodes(User $user): int
    {
        $row = $user->twoFactorAuth()->first();

        return $row ? count($row->recovery_codes ?? []) : 0;
    }

    // ── Disable ────────────────────────────────────────────────────────────

    public function disable(User $user, string $password): void
    {
        $row = $user->twoFactorAuth()->first();

        if (! $row?->isEnabled()) {
            throw new \RuntimeException('Zwei-Faktor-Authentifizierung ist nicht aktiv.');
        }

        if (! Hash::check($password, $user->password)) {
            throw new \RuntimeException('Das Passwort ist falsch.');
        }

        $row->update([
            'secret'         => null,
            'recovery_codes' => [],
            'confirmed_at'   => null,
            'last_used_at'   => null,
        ]);

        $this->audit->record($user, AuditEventEnum::SecurityChanged,
            oldValues: ['two_factor_enabled' => true],
            newValues: ['two_factor_enabled' => false],
            reason: '2FA deaktiviert',
            actorId: $user->id
        );
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function verifyTotp(?string $secret, string $code): bool
    {
        if (blank($secret)) {
            return false;
        }

        try {
            return $this->google2fa->verifyKey($secret, $code, 1);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Consume a recovery code if it matches a stored hash.
     */
    private function consumeRecoveryCode($row, string $input): bool
    {
        $codes = $row->recovery_codes ?? [];

        if (empty($codes)) {
            return false;
        }

        $hashes = collect($codes);

        $found = $hashes->first(fn($hash) => hash_equals($hash, hash('sha256', strtoupper(trim($input)))));

        if ($found === null) {
            return false;
        }

        $row->update([
            'recovery_codes' => $hashes->reject(fn($hash) => $hash === $found)->values()->all(),
        ]);

        return true;
    }

    /**
     * Generate 10 recovery codes of 10 chars (Base32-style, no ambiguous chars).
     *
     * @return array<string>
     */
    private function generateRecoveryCodes(): array
    {
        $chars = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';
        $codes = [];

        for ($i = 0; $i < 10; $i++) {
            $half = fn() => implode('', array_map(
                fn() => $chars[random_int(0, strlen($chars) - 1)],
                range(1, 5)
            ));

            $codes[] = $half().'-'.$half();
        }

        return $codes;
    }

    /**
     * @param  array<string>  $codes
     * @return array<string>
     */
    private function hashRecoveryCodes(array $codes): array
    {
        return array_map(
            fn(string $code) => hash('sha256', $code),
            $codes
        );
    }

    private function qrSvg(string $uri): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(256),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($uri);
    }
}