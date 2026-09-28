<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | EMS is a trilingual deployment. German is the primary language and the
    | fallback for every user, Arabic is the operational language of the field
    | staff, and English is provided for the customer portal and for staff who
    | prefer it. The order of this list defines the order of the language
    | switcher in the admin dashboard.
    |
    | Every value here must have a matching directory in the `lang/` folder.
    |
    */

    'supported' => ['de', 'ar', 'en'],

    /*
    | The locale every account falls back to when none is chosen explicitly.
    | Also the value enforced by the `users.locale` column default.
    */
    'default' => 'de',

    'fallback' => 'de',

    /*
    | Right-to-left locales. The blade layout flips `dir` and the stylesheet
    | switches its logical properties based on this list.
    */
    'rtl' => ['ar'],

    /*
    | Display metadata used by the language switcher and the mobile app.
    | `native` is the language name written in the language itself.
    */
    'meta' => [
        'de' => ['label' => 'Deutsch',  'native' => 'Deutsch',  'dir' => 'ltr', 'flag' => '🇩🇪'],
        'ar' => ['label' => 'Arabic',   'native' => 'العربية',  'dir' => 'rtl', 'flag' => '🇸🇦'],
        'en' => ['label' => 'English',  'native' => 'English',  'dir' => 'ltr', 'flag' => '🇬🇧'],
    ],

];
