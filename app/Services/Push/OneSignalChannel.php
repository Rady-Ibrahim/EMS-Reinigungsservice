<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;

/**
 * OneSignal delivery channel.
 *
 * Talks to the OneSignal REST API directly rather than going through the
 * notification SDK, so push stays inside the project's own PushChannel
 * contract and NotificationEngineService keeps fanning out over `device_tokens`
 * rows exactly as it did for FCM.
 *
 * Follows the same fail-safe pattern as the former FCM channel: delivery is a
 * silent no-op until OneSignal is configured in .env (PUSH_ENABLED=true +
 * ONESIGNAL_APP_ID + ONESIGNAL_REST_API_KEY). Database notifications are always
 * delivered regardless of push configuration, and a transport error never
 * bubbles into the business operation that triggered the notification.
 */
class OneSignalChannel implements PushChannel
{
    /** Player ids OneSignal no longer recognises, e.g. after an app uninstall. */
    private const INVALID_PLAYER_MARKERS = [
        'invalid player_id',
        'no subscription found',
        'app_id does not match',
        'invalid player id',
    ];

    public function send(DeviceToken $device, array $message): bool
    {
        if (! $this->isConfigured() || $device->provider !== 'onesignal') {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Rest '.config('push.onesignal.rest_api_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout((int) config('push.onesignal.timeout', 10))
                ->post(config('push.onesignal.api_url'), $this->payload($device, $message));
        } catch (\Throwable) {
            return false;
        }

        if ($response->successful()) {
            $device->forceFill(['last_used_at' => now()])->saveQuietly();

            return true;
        }

        // A player id the SDK no longer knows about must not sit in the table
        // forever — it would consume one of the user's five device slots and
        // fail again on every future notification.
        if ($this->isInvalidPlayer($response->body())) {
            $device->forceFill(['is_active' => false])->saveQuietly();
        }

        return false;
    }

    private function isConfigured(): bool
    {
        return (bool) config('push.enabled')
            && (bool) config('push.onesignal.app_id')
            && (bool) config('push.onesignal.rest_api_key');
    }

    /**
     * @param  array{title: string, body?: string, data?: array}  $message
     */
    private function payload(DeviceToken $device, array $message): array
    {
        $locale = $this->locale($device);

        return [
            'app_id' => config('push.onesignal.app_id'),
            'include_player_ids' => [$device->token],
            'headings' => [$locale => $message['title']],
            'contents' => [$locale => $message['body'] ?? ''],
            'data' => $message['data'] ?? [],
            'priority' => 10,
            'target_channel' => 'Push',
        ];
    }

    /**
     * OneSignal indexes heading/content maps by language code. The device row
     * does not carry a locale, so the signed-in crew member's own preference is
     * used and German is the fallback.
     */
    private function locale(DeviceToken $device): string
    {
        $locale = $device->user?->locale;

        if (! is_string($locale) || $locale === '') {
            return config('app.fallback_locale', 'de');
        }

        return $locale;
    }

    private function isInvalidPlayer(string $body): bool
    {
        $needle = mb_strtolower($body);

        foreach (self::INVALID_PLAYER_MARKERS as $marker) {
            if (str_contains($needle, $marker)) {
                return true;
            }
        }

        return false;
    }
}
