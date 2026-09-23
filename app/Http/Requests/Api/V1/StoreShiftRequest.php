<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id'  => ['required', 'exists:users,id'],
            'title'    => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at'   => ['required', 'date', 'after:start_at'],
            'all_day'  => ['nullable', 'boolean'],
            'notes'    => ['nullable', 'string', 'max:2000'],
        ];
    }
}