<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditEventEnum;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The "Me / Einstellungen" screen of the mobile app.
 *
 * An employee may change their display language and their own password.
 * Everything else on the account is administration territory.
 */
class PreferenceController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * GET /api/v1/me/preferences
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'locale'          => $user->locale,
                'locales'         => $this->availableLocales(),
                'notifications'   => [
                    'push_enabled' => $user->deviceTokens()->active()->exists(),
                ],
            ],
        ], Response::HTTP_OK);
    }

    /**
     * PUT /api/v1/me/preferences
     *
     * Accepts `locale` and/or `password`. Changing the password requires the
     * current one to be supplied, and revokes every other API token so other
     * devices of a stolen phone are locked out immediately.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'locale'             => ['sometimes', 'required', 'string', Rule::in(Locales::supported())],
            'password'           => ['sometimes', 'required', 'string', 'min:8', 'confirmed', 'different:current_password'],
            'current_password'   => ['required_with:password', 'string'],
        ], [], [
            'locale'           => __('validation.attributes.locale'),
            'password'         => __('validation.attributes.password'),
            'current_password' => __('validation.attributes.current_password'),
        ]);

        $changed = [];

        // ── Language ───────────────────────────────────────────────────────
        if (array_key_exists('locale', $validated)) {
            if ($validated['locale'] !== $user->locale) {
                $previous = $user->locale;

                $user->forceFill(['locale' => $validated['locale']])->save();

                $changed['locale'] = ['old' => $previous, 'new' => $validated['locale']];

                $this->audit->record(
                    $user,
                    AuditEventEnum::Updated,
                    oldValues: ['locale' => $previous],
                    newValues: ['locale' => $validated['locale']],
                    actorId: $user->id,
                );
            }
        }

        // ── Password ───────────────────────────────────────────────────────
        $tokensRevoked = 0;

        if (array_key_exists('password', $validated)) {
            if (! Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => __('validation.current_password_incorrect'),
                ]);
            }

            $user->forceFill(['password' => Hash::make($validated['password'])])->save();

            // Every other API token is revoked so that a stolen phone is locked
            // out immediately. `currentAccessToken()` is null when the request
            // was not authenticated with a Sanctum personal access token; in
            // that case there is no token of ours worth keeping, so ALL of the
            // user's tokens are dropped. Filtering on a null id would compile to
            // `id != NULL`, which matches nothing and would silently revoke none.
            $currentTokenId = $user->currentAccessToken()?->id;

            $tokenQuery = $user->tokens();

            if ($currentTokenId !== null) {
                $tokenQuery->where('id', '!=', $currentTokenId);
            }

            $tokensRevoked = $tokenQuery->delete();

            $this->audit->record(
                $user,
                AuditEventEnum::SecurityChanged,
                newValues: ['password' => 'changed', 'tokens_revoked' => $tokensRevoked],
                actorId: $user->id,
            );
        }

        return response()->json([
            'message' => __('messages.preferences.updated'),
            'data'    => [
                'locale'          => $user->fresh()->locale,
                'locales'         => $this->availableLocales(),
                'changed'         => array_keys($changed),
                'tokens_revoked'  => $tokensRevoked,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * @return array<int, array{code: string, label: string, native: string, dir: string}>
     */
    private function availableLocales(): array
    {
        return collect(Locales::meta())
            ->map(fn(array $meta, string $code) => [
                'code'   => $code,
                'label'  => $meta['label'],
                'native' => $meta['native'],
                'dir'    => $meta['dir'],
            ])
            ->values()
            ->all();
    }
}
