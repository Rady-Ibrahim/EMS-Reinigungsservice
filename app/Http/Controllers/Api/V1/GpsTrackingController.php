<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BatchGpsPointsRequest;
use App\Http\Requests\Api\V1\RecordGpsPointRequest;
use App\Models\TravelTrack;
use App\Services\GpsTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GpsTrackingController extends Controller
{
    public function __construct(private readonly GpsTrackingService $gpsService)
    {
    }

    /**
     * POST /api/v1/travel-tracks/{track}/gps
     * Record a single GPS point during travel.
     * Validates ownership — employee can only add to their own track.
     */
    public function store(RecordGpsPointRequest $request, TravelTrack $travelTrack): JsonResponse
    {
        abort_unless($travelTrack->user_id === $request->user()->id, Response::HTTP_FORBIDDEN);

        // Only allow tracking on active (non-arrived) tracks
        if ($travelTrack->hasArrived()) {
            return response()->json([
                'message' => 'Diese Fahrt ist bereits abgeschlossen.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $point = $this->gpsService->recordPoint($travelTrack, $request->validated());

        return response()->json([
            'message' => 'GPS-Punkt gespeichert.',
            'data'    => [
                'id'              => $point->id,
                'latitude'        => $point->latitude,
                'longitude'       => $point->longitude,
                'recorded_at'     => $point->recorded_at->toIso8601String(),
                'accuracy_meters' => $point->accuracy_meters,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * POST /api/v1/travel-tracks/{track}/gps/batch
     * Batch-record GPS points (offline sync).
     */
    public function storeBatch(BatchGpsPointsRequest $request, TravelTrack $travelTrack): JsonResponse
    {
        abort_unless($travelTrack->user_id === $request->user()->id, Response::HTTP_FORBIDDEN);

        $count = $this->gpsService->recordBatch($travelTrack, $request->input('points'));

        return response()->json([
            'message'        => "{$count} GPS-Punkte gespeichert.",
            'points_created' => $count,
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/v1/travel-tracks/{track}/gps
     * Retrieve all GPS points for a travel track (for map rendering).
     * Returns GeoJSON for easy map integration.
     */
    public function index(Request $request, TravelTrack $travelTrack): JsonResponse
    {
        abort_unless($travelTrack->user_id === $request->user()->id, Response::HTTP_FORBIDDEN);

        $geoJson  = $this->gpsService->toGeoJson($travelTrack);
        $distance = $this->gpsService->totalTrackDistance($travelTrack);

        return response()->json([
            'data' => [
                'geojson'          => $geoJson,
                'total_distance_km'=> $distance,
                'point_count'      => $travelTrack->gpsPoints()->count(),
            ],
        ]);
    }
}
