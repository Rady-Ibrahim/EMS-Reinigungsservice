<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BatchGpsPointsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'points'                  => ['required', 'array', 'min:1', 'max:500'],
            'points.*.latitude'       => ['required', 'numeric', 'between:-90,90'],
            'points.*.longitude'      => ['required', 'numeric', 'between:-180,180'],
            'points.*.recorded_at'    => ['required', 'date'],
            'points.*.accuracy_meters'=> ['nullable', 'numeric', 'min:0'],
        ];
    }
}
