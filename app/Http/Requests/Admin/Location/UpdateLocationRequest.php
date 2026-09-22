<?php

namespace App\Http\Requests\Admin\Location;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:255'],
            'street'              => ['required', 'string', 'max:255'],
            'house_number'        => ['required', 'string', 'max:20'],
            'postal_code'         => ['required', 'string', 'max:10'],
            'city'                => ['required', 'string', 'max:100'],
            'country'             => ['nullable', 'string', 'max:5'],
            'latitude'            => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'           => ['nullable', 'numeric', 'between:-180,180'],
            'contact_person'      => ['nullable', 'string', 'max:255'],
            'contact_phone'       => ['nullable', 'string', 'max:30'],
            'access_instructions' => ['nullable', 'string'],
            'security_code'       => ['nullable', 'string', 'max:100'],
            'service_checklist'   => ['nullable', 'array'],
            'service_checklist.*' => ['string', 'max:255'],
            'working_days'        => ['nullable', 'array'],
            'working_days.*'      => ['string', 'in:Mon,Tue,Wed,Thu,Fri,Sat,Sun'],
            'working_hours_start' => ['nullable', 'date_format:H:i'],
            'working_hours_end'   => ['nullable', 'date_format:H:i', 'after:working_hours_start'],
            'is_active'           => ['boolean'],
        ];
    }
}
