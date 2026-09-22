<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * POST /api/v1/auth/login
     *
     * For mobile users: Vorarbeiter & Mitarbeiter.
     * Returns a Sanctum token + user data.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->attemptLogin($request->only('email', 'password'));

        if (! $user) {
            return response()->json([
                'message' => __('auth.failed'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => __('auth.inactive'),
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $user->isApiUser()) {
            return response()->json([
                'message' => __('auth.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }

        $this->authService->recordLogin($user);
        $tokenData = $this->authService->createApiToken($user);

        return response()->json([
            'message' => __('auth.login_success'),
            'data'    => [
                'user' => [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'email'  => $user->email,
                    'role'   => $user->role->value,
                    'locale' => $user->locale,
                ],
                'token'      => $tokenData['token'],
                'token_type' => $tokenData['token_type'],
            ],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => __('auth.logout_success'),
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/v1/auth/me
     *
     * Return the authenticated user's basic info.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'role'          => $user->role->value,
                'role_label'    => $user->role->label(),
                'locale'        => $user->locale,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
        ], Response::HTTP_OK);
    }
}
