<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(string $locale, Request $request): RedirectResponse
    {
        $supported = (array) config('locales.supported', ['de', 'ar', 'en']);
        $default   = (string) config('locales.default', 'de');

        $locale = in_array($locale, $supported, true) ? $locale : $default;

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }

        $request->session()->put('locale', $locale);

        return redirect()->back();
    }
}
