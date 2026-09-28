<?php

namespace App\Support;

use App\Models\User;

/**
 * Single source of truth for the trilingual deployment (de / ar / en).
 *
 * Kept in one place so the middleware, the switcher, the validation rules and
 * the mobile API can never drift apart on what is actually supported.
 */
final class Locales
{
    /**
     * @return array<int, string>
     */
    public static function supported(): array
    {
        return (array) config('locales.supported', ['de', 'ar', 'en']);
    }

    public static function default(): string
    {
        $default = (string) config('locales.default', 'de');

        return self::isSupported($default) ? $default : 'de';
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::supported(), true);
    }

    /**
     * Normalise an arbitrary input to a supported locale, or null if invalid.
     */
    public static function normalize(?string $locale): ?string
    {
        return self::isSupported($locale) ? $locale : null;
    }

    public static function isRtl(?string $locale): bool
    {
        return $locale !== null && in_array($locale, (array) config('locales.rtl', ['ar']), true);
    }

    public static function direction(?string $locale): string
    {
        return self::isRtl($locale) ? 'rtl' : 'ltr';
    }

    /**
     * Display metadata for the language switcher.
     *
     * @return array<string, array{label: string, native: string, dir: string, flag: string}>
     */
    public static function meta(): array
    {
        $meta = (array) config('locales.meta', []);

        return collect(self::supported())
            ->mapWithKeys(fn(string $code) => [$code => $meta[$code] ?? [
                'label'  => strtoupper($code),
                'native' => strtoupper($code),
                'dir'    => self::direction($code),
                'flag'   => '',
            ]])
            ->all();
    }

    /**
     * Resolve the locale that should be used for a given user.
     */
    public static function forUser(?User $user): string
    {
        if ($user && self::isSupported($user->locale)) {
            return $user->locale;
        }

        return self::default();
    }
}
