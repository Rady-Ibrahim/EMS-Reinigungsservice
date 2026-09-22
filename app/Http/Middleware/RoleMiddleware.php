<?php

namespace App\Http\Middleware;

use App\Enums\RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Usage in routes:
     *   ->middleware('role:administrator')
     *   ->middleware('role:administrator,vorarbeiter')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthorized($request);
        }

        if (! $user->is_active) {
            return $this->inactive($request);
        }

        // Convert string args to RoleEnum values for comparison
        $allowed = array_map(
            fn(string $r) => RoleEnum::from($r),
            $roles
        );

        if (! in_array($user->role, $allowed)) {
            return $this->forbidden($request);
        }

        return $next($request);
    }

    private function unauthorized(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('auth.unauthorized'),
            ], Response::HTTP_UNAUTHORIZED);
        }
        return redirect()->route('admin.login');
    }

    private function inactive(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('auth.inactive'),
            ], Response::HTTP_FORBIDDEN);
        }
        return redirect()->route('login')->withErrors(['email' => __('auth.inactive')]);
    }

    private function forbidden(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('messages.forbidden'),
            ], Response::HTTP_FORBIDDEN);
        }
        return abort(Response::HTTP_FORBIDDEN, __('messages.forbidden'));
    }
}
