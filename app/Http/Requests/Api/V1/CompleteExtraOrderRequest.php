<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CompleteExtraOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'gps_work_end_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_work_end_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'after_photos'     => ['nullable', 'array'],
            'after_photos.*'   => ['string'],
            'employee_notes'   => ['nullable', 'string', 'max:1000'],
            'work_end'         => ['nullable', 'date'],
        ];
    }
}
