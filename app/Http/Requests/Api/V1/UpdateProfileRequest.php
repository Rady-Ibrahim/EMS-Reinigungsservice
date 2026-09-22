<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'in:de,ar,en'],
            'phone'  => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}
