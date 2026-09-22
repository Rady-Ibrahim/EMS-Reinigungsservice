<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    /**
     * GET /api/v1/profile
     * Returns authenticated employee's profile.
     * Sensitive fields (hourly_rate, iban) are NEVER exposed here.
     */
    public function show(Request $request): JsonResponse
    {
        $user    = $request->user()->load('employeeProfile');
        $profile = $user->employeeProfile;

        return response()->json([
            'data' => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'role'   => $user->role->value,
                'locale' => $user->locale,
                'profile' => $profile ? [
                    'calendar_color'  => $profile->calendar_color,
                    'employee_number' => $profile->employee_number,
                    'phone'           => $profile->phone,
                    'contract_type'   => $profile->contract_type?->value,
                    'joined_at'       => $profile->joined_at?->toDateString(),
                    // hourly_rate and iban intentionally omitted
                ] : null,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * PATCH /api/v1/profile
     * Employee can only update locale and phone.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (isset($data['locale'])) {
            $user->update(['locale' => $data['locale']]);
        }

        if (array_key_exists('phone', $data)) {
            $user->employeeProfile?->update(['phone' => $data['phone']]);
        }

        return response()->json([
            'message' => __('messages.success'),
            'data'    => [
                'locale' => $user->fresh()->locale,
            ],
        ], Response::HTTP_OK);
    }
}
