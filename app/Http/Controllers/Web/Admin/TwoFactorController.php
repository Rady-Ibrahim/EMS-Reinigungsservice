<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuthService $service)
    {
    }

    public function setup(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($this->service->isEnabledFor($user)) {
            return redirect()->route('admin.dashboard')
                ->with('success', 'Zwei-Faktor-Authentifizierung ist bereits aktiv.');
        }

        try {
            $data = $this->service->setup($user);

            session()->flash('2fa_secret', $data['secret']);

            return view('admin.two_factor.setup', [
                'qr_svg'          => $data['qr_svg'],
                'secret'          => $data['secret'],
                'remaining_codes' => $this->service->remainingRecoveryCodes($user),
            ]);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['two_factor' => $e->getMessage()]);
        }
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validation = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        try {
            $result = $this->service->confirm($request->user(), $validation['code']);

            return redirect()->route('admin.two-factor.setup')
                ->with('success', 'Zwei-Faktor-Authentifizierung aktiviert.')
                ->with('two_factor_recovery_codes', $result['recovery_codes']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }
    }

    public function disable(Request $request): RedirectResponse
    {
        $validation = $request->validate([
            'password' => ['required', 'string'],
        ]);

        try {
            $this->service->disable($request->user(), $validation['password']);

            return redirect()->route('admin.two-factor.setup')
                ->with('success', 'Zwei-Faktor-Authentifizierung deaktiviert.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['password' => $e->getMessage()]);
        }
    }

    /**
     * Show the recovery codes once more if they were flashed after confirm.
     */
    public function recoveryCodes(Request $request): View
    {
        $codes = session()->pull('two_factor_recovery_codes', []);

        return view('admin.two_factor.recovery_codes', [
            'codes' => $codes,
        ]);
    }
}