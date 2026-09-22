<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocationController extends Controller
{
    /**
     * GET /api/v1/locations
     *
     * Returns all ACTIVE locations.
     * Fields excluded for API consumers:
     *   - security_code (Admin only, encrypted)
     *   - internal notes
     *
     * Vorarbeiter additionally sees: access_instructions
     * Mitarbeiter does NOT see: access_instructions
     */
    public function index(Request $request): JsonResponse
    {
        $user      = $request->user();
        $locations = CustomerLocation::with('customer:id,name')
            ->active()
            ->get()
            ->map(fn($loc) => $this->formatLocation($loc, $user));

        return response()->json([
            'data' => $locations,
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/v1/locations/{id}
     */
    public function show(Request $request, CustomerLocation $location): JsonResponse
    {
        abort_unless($location->is_active, Response::HTTP_NOT_FOUND);

        $location->load('customer:id,name');

        return response()->json([
            'data' => $this->formatLocation($location, $request->user()),
        ], Response::HTTP_OK);
    }

    private function formatLocation(CustomerLocation $loc, $user): array
    {
        $data = [
            'id'                  => $loc->id,
            'name'                => $loc->name,
            'customer'            => [
                'id'   => $loc->customer->id,
                'name' => $loc->customer->name,
            ],
            'address'             => $loc->fullAddress(),
            'city'                => $loc->city,
            'latitude'            => $loc->latitude,
            'longitude'           => $loc->longitude,
            'contact_person'      => $loc->contact_person,
            'contact_phone'       => $loc->contact_phone,
            'working_hours_start' => $loc->working_hours_start,
            'working_hours_end'   => $loc->working_hours_end,
            'working_days'        => $loc->working_days,
            'service_checklist'   => $loc->service_checklist,
        ];

        // Vorarbeiter sees access instructions; Mitarbeiter does not
        if ($user->isVorarbeiter()) {
            $data['access_instructions'] = $loc->access_instructions;
        }

        // security_code is NEVER sent to the mobile app
        return $data;
    }
}
