<?php

namespace App\Services\Push;

use App\Models\DeviceToken;

/**
 * Contract for a push delivery channel (FCM, WebPush, ...).
 * Implementations MUST be fail-safe — a delivery failure must never break the
 * underlying business operation.
 */
interface PushChannel
{
    /**
     * Deliver a push message to a single device.
     *
     * @param  array{title: string, body: string, data: array}  $message
     */
    public function send(DeviceToken $device, array $message): bool;
}