<?php

namespace App\Http\Requests\Admin\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id'  => ['required', 'exists:users,id'],
            'title'    => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at'   => ['required', 'date', 'after:start_at'],
            'all_day'  => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'color'    => ['nullable', 'string', 'max:15'],
        ];
    }
}