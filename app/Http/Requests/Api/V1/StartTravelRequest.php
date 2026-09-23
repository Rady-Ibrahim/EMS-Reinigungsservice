<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StartTravelRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'gps_departure_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_departure_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'offline_uuid'      => ['nullable', 'uuid'],
            'departure_at'      => ['nullable', 'date'],
        ];
    }
}
