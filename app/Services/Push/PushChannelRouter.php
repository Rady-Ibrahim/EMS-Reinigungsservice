<?php

namespace App\Services\Push;

use App\Models\DeviceToken;

/**
 * Dispatches a push message to the channel that owns the device's provider.
 *
 * NotificationEngineService fans out over `device_tokens` rows and does not care
 * which vendor is behind them, so swapping or adding a provider only happens
 * here. Binding this to PushChannel in AppServiceProvider keeps every existing
 * sendToUser() / sendToEmployees() call site on the OneSignal path without
 * touching a single service.
 */
class PushChannelRouter implements PushChannel
{
    public function __construct(
        private readonly OneSignalChannel $oneSignal,
    ) {}

    public function send(DeviceToken $device, array $message): bool
    {
        return match ($device->provider) {
            'onesignal' => $this->oneSignal->send($device, $message),
            // 'webpush' is reserved (VAPID keys exist in config) but has no
            // channel implementation yet — a silent no-op, as before.
            default => false,
        };
    }
}
