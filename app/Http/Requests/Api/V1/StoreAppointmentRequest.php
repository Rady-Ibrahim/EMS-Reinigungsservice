<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'start_at'    => ['required', 'date'],
            'end_at'      => ['required', 'date', 'after:start_at'],
            'all_day'     => ['nullable', 'boolean'],
            'location'    => ['nullable', 'string', 'max:255'],
            'color'       => ['nullable', 'string', 'max:15'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}