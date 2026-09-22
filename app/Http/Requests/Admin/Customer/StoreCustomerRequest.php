<?php

namespace App\Http\Requests\Admin\Customer;

use App\Enums\CustomerStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate handled by route middleware
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
        ];
    }
}
