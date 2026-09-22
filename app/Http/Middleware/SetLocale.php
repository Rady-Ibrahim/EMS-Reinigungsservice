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
     *  3. App default (de)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);
        App::setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $supported = ['de', 'ar'];

        // 1. Authenticated user preference
        if ($request->user() && in_array($request->user()->locale, $supported)) {
            return $request->user()->locale;
        }

        // 2. Session value — only available on stateful (web) requests
        if ($request->hasSession()) {
            $sessionLocale = $request->session()->get('locale');
            if (in_array($sessionLocale, $supported)) {
                return $sessionLocale;
            }
        }

        // 3. Fallback to app default
        return config('app.locale', 'de');
    }
}
