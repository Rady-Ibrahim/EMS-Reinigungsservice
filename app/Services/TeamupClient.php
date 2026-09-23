<?php

namespace App\Services;

use App\Models\TeamupSetting;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP wrapper around the Teamup REST API v4
 * (https://api.teamup.com/{calendarKey}/…).
 *
 * Authentication: `Teamup-Token` header carries the developer API key;
 * access to the calendar is granted via the secret calendar key (prefix `ks`).
 */
class TeamupClient
{
    private const BASE_URL = 'https://api.teamup.com';

    public function settings(): TeamupSetting
    {
        return TeamupSetting::current();
    }

    /**
     * @param  array<string, mixed>  $payload  query params for GET, body otherwise
     */
    private function request(string $method, string $path, array $payload = []): Response
    {
        $settings = $this->settings();

        if (! $settings->enabled || empty($settings->calendar_key) || empty($settings->api_key)) {
            throw new RuntimeException('Teamup ist nicht konfiguriert.');
        }

        return Http::baseUrl(self::BASE_URL)
            ->withHeaders(['Teamup-Token' => $settings->api_key])
            ->asJson()
            ->{$method}($path, $payload);
    }

    /**
     * GET /{calendarKey}/events?startDate=…&endDate=…
     */
    public function fetchEvents(CarbonInterface $from, CarbonInterface $to): array
    {
        $response = $this->request('get', '/'.$this->settings()->calendar_key.'/events', [
            'startDate' => $from->toDateString(),
            'endDate'   => $to->toDateString(),
        ]);
        $response->throw();

        return $response->json('events', []);
    }

    /**
     * GET /{calendarKey}/events?modifiedSince={unix}
     * Returns events created/updated/deleted since the given timestamp.
     */
    public function fetchChanged(int $modifiedSinceUnix): array
    {
        $response = $this->request('get', '/'.$this->settings()->calendar_key.'/events', [
            'modifiedSince' => $modifiedSinceUnix,
        ]);
        $response->throw();

        return $response->json('events', []);
    }

    /**
     * POST /{calendarKey}/events — create event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createEvent(array $payload): array
    {
        $response = $this->request('post', '/'.$this->settings()->calendar_key.'/events', $payload);
        $response->throw();

        return $response->json('event', []);
    }

    /**
     * PUT /{calendarKey}/events/{eventId} — update event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateEvent(string $eventId, array $payload): array
    {
        $response = $this->request('put', '/'.$this->settings()->calendar_key.'/events/'.$eventId, $payload);
        $response->throw();

        return $response->json('event', []);
    }

    /**
     * DELETE /{calendarKey}/events/{eventId}
     */
    public function deleteEvent(string $eventId): void
    {
        $response = $this->request('delete', '/'.$this->settings()->calendar_key.'/events/'.$eventId);
        $response->throw();
    }

    /**
     * GET /check-access — validates the developer API key only.
     */
    public function checkAccess(): bool
    {
        $response = Http::get(self::BASE_URL.'/check-access', [
            '_teamup_token' => $this->settings()->api_key,
        ]);

        return $response->successful();
    }
}