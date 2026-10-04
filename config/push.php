<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Push Notifications (OneSignal)
    |--------------------------------------------------------------------------
    | Push delivery stays a silent no-op until the credentials below are
    | configured and push.enabled is set to true. Database notifications always
    | work regardless — a push failure must never break a business operation.
    */

    'enabled' => env('PUSH_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | OneSignal REST API
    |--------------------------------------------------------------------------
    | `rest_api_key` is the REST API key from the OneSignal dashboard
    | (Settings > API Keys), NOT the SDK "User Auth Key". Values mirror
    | config/services.php so the notification SDK and the push engine stay in
    | sync from a single pair of env vars.
    */
    'onesignal' => [
        'app_id' => env('ONESIGNAL_APP_ID'),
        'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
        'api_url' => env('ONESIGNAL_API_URL', 'https://api.onesignal.com/notifications'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WebPush (VAPID) — reserved for the admin dashboard / web workers.
    */
    'webpush' => [
        'vapid_public_key' => env('VAPID_PUBLIC_KEY'),
        'vapid_private_key' => env('VAPID_PRIVATE_KEY'),
        'vapid_subject' => env('VAPID_SUBJECT', 'mailto:admin@ems.local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery tuning
    |--------------------------------------------------------------------------
    | Cap on how many devices NotificationEngineService fans out to per user.
    | Each active subscription is delivered in its own request, so this is the
    | only knob that bounds the per-user request count.
    */
    'max_devices_per_user' => (int) env('PUSH_MAX_DEVICES', 5),
];
