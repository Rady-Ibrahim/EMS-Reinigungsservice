<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Priority order:
     *  1. Authenticated user's saved locale preference
     *  2. Session locale (set explicitly, e.g. language switcher)
     *  3. Explicit ?locale= override (used by the mobile app on first launch)
     *  4. App default (de)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);
        App::setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $supported = $this->supported();

        // 1. Authenticated user preference
        if ($request->user() && in_array($request->user()->locale, $supported, true)) {
            return $request->user()->locale;
        }

        // 2. Query override — lets the mobile app request a specific language
        $requested = $request->query('locale');
        if (is_string($requested) && in_array($requested, $supported, true)) {
            return $requested;
        }

        // 3. Session value — only available on stateful (web) requests
        if ($request->hasSession()) {
            $sessionLocale = $request->session()->get('locale');
            if (in_array($sessionLocale, $supported, true)) {
                return $sessionLocale;
            }
        }

        // 4. Fallback to app default
        $default = (string) config('locales.default', 'de');

        return in_array($default, $supported, true) ? $default : 'de';
    }

    /**
     * @return array<int, string>
     */
    private function supported(): array
    {
        return (array) config('locales.supported', ['de', 'ar', 'en']);
    }
}
