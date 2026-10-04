<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\Contracts\PushableNotification;
use App\Services\Push\OneSignalChannel;
use Illuminate\Notifications\Notification;

/**
 * Laravel Notification channel that hands the message to the project's own
 * OneSignal channel, once per active `device_tokens` row.
 *
 * This is what keeps the Laravel Notification API and the existing
 * NotificationEngineService on a single delivery path: both end up calling
 * App\Services\Push\OneSignalChannel, so there is exactly one place that knows
 * how to talk to OneSignal and one place that decides who receives what.
 */
class OneSignalDeviceChannel
{
    public function __construct(
        private readonly OneSignalChannel $oneSignal,
    ) {}

    public function send($notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof PushableNotification) {
            return;
        }

        $devices = $notifiable->deviceTokens()
            ->activeOneSignal()
            ->orderByDesc('last_used_at')
            ->limit((int) config('push.max_devices_per_user', 5))
            ->get();

        if ($devices->isEmpty()) {
            return;
        }

        $message = $notification->toPushMessage();

        foreach ($devices as $device) {
            $this->oneSignal->send($device, $message);
        }
    }
}
