<?php

namespace App\Http\Requests\Admin\Customer;

use App\Enums\CustomerStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'status'         => ['required', Rule::in(CustomerStatusEnum::values())],
            'notes'          => ['nullable', 'string'],
            'portal_access'  => ['boolean'],
            'portal_email'   => ['nullable', 'email', 'max:255', 'required_if:portal_access,true'],

            'locations'                        => ['nullable', 'array'],
            'locations.*.id'                   => ['nullable', 'integer', 'exists:customer_locations,id'],
            'locations.*.name'                 => ['required', 'string', 'max:255'],
            'locations.*.street'               => ['required', 'string', 'max:255'],
            'locations.*.house_number'         => ['required', 'string', 'max:20'],
            'locations.*.postal_code'          => ['required', 'string', 'max:10'],
            'locations.*.city'                 => ['required', 'string', 'max:100'],
            'locations.*.contact_person'       => ['nullable', 'string', 'max:255'],
            'locations.*.contact_phone'        => ['nullable', 'string', 'max:30'],
            'locations.*.access_instructions'  => ['nullable', 'string'],
            'locations.*.security_code'        => ['nullable', 'string', 'max:100'],
        ];
    }
}
