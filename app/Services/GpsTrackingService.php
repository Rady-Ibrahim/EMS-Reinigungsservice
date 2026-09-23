<?php

namespace App\Services;

use App\Models\GpsPoint;
use App\Models\TravelTrack;
use Illuminate\Support\Collection;

/**
 * Pure service for GPS operations.
 * Handles point recording, validation, and distance calculations.
 * No authentication logic — caller is responsible for auth checks.
 */
class GpsTrackingService
{
    private const EARTH_RADIUS_KM = 6371.0;

    // ── Point recording ────────────────────────────────────────────────────

    /**
     * Record a single GPS breadcrumb point during travel.
     * Does NOT block invalid points — logs with accuracy for later review.
     */
    public function recordPoint(TravelTrack $track, array $data): GpsPoint
    {
        return GpsPoint::create([
            'travel_track_id' => $track->id,
            'user_id'         => $track->user_id,
            'latitude'        => $data['latitude'],
            'longitude'       => $data['longitude'],
            'recorded_at'     => $data['recorded_at'] ?? now(),
            'accuracy_meters' => $data['accuracy_meters'] ?? null,
            'created_at'      => now(),
        ]);
    }

    /**
     * Batch-record multiple GPS points (used for offline sync).
     * Points are inserted in recorded_at order.
     *
     * @param  array  $points  — each: {latitude, longitude, recorded_at, accuracy_meters?}
     * @return int  Number of points inserted
     */
    public function recordBatch(TravelTrack $track, array $points): int
    {
        $rows = [];
        $now  = now()->toDateTimeString();

        // Sort by recorded_at before insert
        usort($points, fn($a, $b) => strtotime($a['recorded_at']) - strtotime($b['recorded_at']));

        foreach ($points as $point) {
            if (! $this->validateCoordinates($point['latitude'] ?? null, $point['longitude'] ?? null)) {
                continue;
            }

            $rows[] = [
                'travel_track_id' => $track->id,
                'user_id'         => $track->user_id,
                'latitude'        => $point['latitude'],
                'longitude'       => $point['longitude'],
                'recorded_at'     => \Carbon\Carbon::parse($point['recorded_at'])->format('Y-m-d H:i:s'),
                'accuracy_meters' => $point['accuracy_meters'] ?? null,
                'created_at'      => $now,
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        GpsPoint::insert($rows);
        return count($rows);
    }

    // ── Distance calculation ───────────────────────────────────────────────

    /**
     * Haversine formula — distance between two coordinates in kilometers.
     */
    public function calculateDistance(
        float $lat1, float $lng1,
        float $lat2, float $lng2
    ): float {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round(self::EARTH_RADIUS_KM * $c, 4);
    }

    /**
     * Total distance of a travel track in kilometers (sum of all segment distances).
     * Returns 0.0 if fewer than 2 points are recorded.
     */
    public function totalTrackDistance(TravelTrack $track): float
    {
        $points = $track->gpsPoints()->get(['latitude', 'longitude', 'recorded_at']);

        if ($points->count() < 2) {
            return 0.0;
        }

        $total = 0.0;
        $prev  = null;

        foreach ($points as $point) {
            if ($prev !== null) {
                $total += $this->calculateDistance(
                    (float) $prev->latitude,
                    (float) $prev->longitude,
                    (float) $point->latitude,
                    (float) $point->longitude
                );
            }
            $prev = $point;
        }

        return round($total, 3);
    }

    /**
     * Check if a coordinate is within a radius of a center point.
     * Used for soft geofencing checks (logging only — never blocking).
     */
    public function isWithinRadius(
        float $lat, float $lng,
        float $centerLat, float $centerLng,
        float $radiusMeters
    ): bool {
        $distanceKm = $this->calculateDistance($lat, $lng, $centerLat, $centerLng);
        return ($distanceKm * 1000) <= $radiusMeters;
    }

    /**
     * Validate that coordinates are within physically possible range.
     */
    public function validateCoordinates(mixed $lat, mixed $lng): bool
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return false;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }

    /**
     * Build a GeoJSON LineString from a track's GPS points.
     * Useful for map rendering in Phase 6.
     */
    public function toGeoJson(TravelTrack $track): array
    {
        $coordinates = $track->gpsPoints()
            ->get(['longitude', 'latitude'])
            ->map(fn($p) => [(float) $p->longitude, (float) $p->latitude])
            ->values()
            ->toArray();

        return [
            'type'     => 'Feature',
            'geometry' => [
                'type'        => 'LineString',
                'coordinates' => $coordinates,
            ],
            'properties' => [
                'travel_track_id' => $track->id,
                'user_id'         => $track->user_id,
            ],
        ];
    }
}
