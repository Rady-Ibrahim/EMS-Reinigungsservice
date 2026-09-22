<?php

namespace App\Http\Controllers\Web\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * Show the admin login form.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->isAdministrator()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    /**
     * Handle admin login — web session based.
     * Non-admin users are rejected here (they use the API endpoint).
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $user = $this->authService->attemptLogin($request->only('email', 'password'));

        if (! $user) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ])->onlyInput('email');
        }

        if (! $user->is_active) {
            return back()->withErrors([
                'email' => __('auth.inactive'),
            ])->onlyInput('email');
        }

        if (! $user->isAdministrator()) {
            return back()->withErrors([
                'email' => __('auth.unauthorized'),
            ])->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $this->authService->recordLogin($user);

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Log the admin out and invalidate the session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
