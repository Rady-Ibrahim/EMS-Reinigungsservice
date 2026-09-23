<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;

/**
 * Firebase Cloud Messaging (HTTP v1) channel.
 *
 * Follows the Teamup pattern: delivery stays a silent no-op until FCM is
 * configured in .env (PUSH_ENABLED=true + FCM_PROJECT_ID + FCM_CREDENTIALS)
 * and a valid service-account JSON is reachable. Database notifications are
 * always delivered regardless of push configuration.
 */
class FcmChannel implements PushChannel
{
    public function send(DeviceToken $device, array $message): bool
    {
        if (! config('push.enabled') || $device->provider !== 'fcm') {
            return false;
        }

        $projectId = config('push.fcm.project_id');
        $credentialsPath = config('push.fcm.credentials');

        if (! $projectId || ! $credentialsPath || ! is_file($credentialsPath)) {
            return false;
        }

        $accessToken = $this->accessToken($credentialsPath);

        if (! $accessToken) {
            return false;
        }

        $url = sprintf(rtrim(config('push.fcm.api_url'), '/') ?: 'https://fcm.googleapis.com/v1/projects/%s/messages:send', $projectId);

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post($url, $this->payload($device, $message));

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function payload(DeviceToken $device, array $message): array
    {
        $base = array_filter([['title' => $message['title'], 'body' => $message['body'] ?? null]], fn($v) => $v['body'] !== null);

        if (strtolower($device->platform) === 'web') {
            return [
                'message' => [
                    'token'        => $device->token,
                    'notification' => ['title' => $message['title'], 'body' => $message['body'] ?? ''],
                    'data'         => $message['data'] ?? [],
                    'webpush'      => ['headers' => ['TTL' => '86400']],
                ],
            ];
        }

        return [
            'message' => [
                'token'        => $device->token,
                'notification' => ['title' => $message['title'], 'body' => $message['body'] ?? ''],
                'data'         => $message['data'] ?? [],
                'android'      => ['priority' => 'high'],
                'apns'         => ['headers' => ['apns-priority' => '10']],
            ],
        ];
    }

    /**
     * Mint a short-lived OAuth2 access token from a Firebase service account
     * (RS256 JWT signed with the account's private key).
     */
    private function accessToken(string $credentialsPath): ?string
    {
        try {
            $account = json_decode(file_get_contents($credentialsPath), true);

            if (empty($account['client_email']) || empty($account['private_key']) || empty($account['token_uri'])) {
                return null;
            }

            $now = \Carbon\CarbonImmutable::now();

            $header = ['alg' => 'RS256', 'typ' => 'JWT'];
            $claims = [
                'iss'   => $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => $account['token_uri'],
                'iat'   => $now->getTimestamp(),
                'exp'   => $now->addMinutes(55)->getTimestamp(),
            ];

            $unsigned = $this->base64Url(json_encode($header)).'.'.$this->base64Url(json_encode($claims));

            openssl_sign($unsigned, $signature, $account['private_key'], OPENSSL_ALGO_SHA256);

            $jwt = $unsigned.'.'.$this->base64Url($signature);

            $response = Http::asForm()->post($account['token_uri'], [
                'grant_type'    => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'     => $jwt,
            ]);

            return $response->json('access_token');
        } catch (\Throwable) {
            return null;
        }
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}