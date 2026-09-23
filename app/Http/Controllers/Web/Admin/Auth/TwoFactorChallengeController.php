<?php

namespace App\Http\Controllers\Web\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Second step of admin login — TOTP challenge entered AFTER the password.
 * The user is not yet authenticated; only `session('2fa.user_id')` is set.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthService $service,
        private readonly AuthService $authService
    ) {
    }

    public function showChallenge(): View|RedirectResponse
    {
        $userId = session('2fa.user_id');

        if (! $userId || ! ($user = User::find($userId)) || ! $this->service->isEnabledFor($user)) {
            return redirect()->route('admin.login');
        }

        return view('admin.two_factor.challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $userId = session('2fa.user_id');

        if (! $userId || ! ($user = User::find($userId))) {
            return redirect()->route('admin.login');
        }

        $validation = $request->validate([
            'code' => ['required', 'string'],
        ]);

        if (! $this->service->challenge($user, $validation['code'])) {
            return back()->withErrors(['code' => 'Der Code ist ungültig.']);
        }

        $remember = (bool) session('2fa.remember', false);
        $intended = session('2fa.intended');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $this->authService->recordLogin($user);

        $request->session()->forget(['2fa.user_id', '2fa.remember', '2fa.intended']);

        return redirect()->to($intended ?? route('admin.dashboard'));
    }
}