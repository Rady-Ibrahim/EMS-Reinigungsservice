<?php

namespace App\Notifications\Contracts;

/**
 * A notification that can be rendered into a push payload.
 *
 * App\Notifications\Channels\OneSignalDeviceChannel type-checks against this so
 * a notification can never be routed to a device channel without knowing how to
 * describe itself.
 */
interface PushableNotification
{
    /**
     * @return array{title: string, body?: string, data?: array}
     */
    public function toPushMessage(): array;
}
