<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StartExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gps_start_lat'       => ['nullable', 'numeric', 'between:-90,90'],
            'gps_start_lng'       => ['nullable', 'numeric', 'between:-180,180'],
            'offline_uuid'        => ['nullable', 'uuid'],
            'client_submitted_at' => ['nullable', 'date'],
        ];
    }
}
