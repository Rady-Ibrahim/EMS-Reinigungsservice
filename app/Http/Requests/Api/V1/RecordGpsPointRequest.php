<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RecordGpsPointRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'latitude'        => ['required', 'numeric', 'between:-90,90'],
            'longitude'       => ['required', 'numeric', 'between:-180,180'],
            'recorded_at'     => ['nullable', 'date'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ];
    }
}
