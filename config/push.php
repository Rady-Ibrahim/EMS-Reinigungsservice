<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Push Notifications (Firebase Cloud Messaging / WebPush)
    |--------------------------------------------------------------------------
    | Push delivery stays a silent no-op until the credentials below are
    | configured and push.enabled is set to true — mirroring the Teamup
    | integration pattern. Database notifications always work.
    */

    'enabled' => env('PUSH_ENABLED', false),

    /*
    | FCM HTTP v1 — path to the Firebase service-account JSON.
    */
    'fcm' => [
        'project_id'    => env('FCM_PROJECT_ID'),
        'credentials'   => env('FCM_CREDENTIALS'),     // absolute path to service-account.json
        'api_url'       => env('FCM_API_URL', 'https://fcm.googleapis.com/v1/projects/%s/messages:send'),
    ],

    /*
    | WebPush (VAPID) — reserved for the admin dashboard / web workers.
    */
    'webpush' => [
        'vapid_public_key'  => env('VAPID_PUBLIC_KEY'),
        'vapid_private_key' => env('VAPID_PRIVATE_KEY'),
        'vapid_subject'     => env('VAPID_SUBJECT', 'mailto:admin@ems.local'),
    ],

    /*
    | Cap on how many devices NotificationEngineService fans out to per user.
    */
    'max_devices_per_user' => (int) env('PUSH_MAX_DEVICES', 5),
];