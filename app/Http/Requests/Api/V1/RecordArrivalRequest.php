<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RecordArrivalRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'gps_arrival_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_arrival_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'arrival_at'      => ['nullable', 'date'],
        ];
    }
}
