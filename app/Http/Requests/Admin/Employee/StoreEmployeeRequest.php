<?php

namespace App\Http\Requests\Admin\Employee;

use App\Enums\ContractTypeEnum;
use App\Enums\RoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // User fields
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()],
            'role'     => ['required', Rule::in([RoleEnum::Vorarbeiter->value, RoleEnum::Mitarbeiter->value])],
            'locale'   => ['required', 'in:de,ar,en'],

            // Profile fields
            'calendar_color'  => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'employee_number' => ['nullable', 'string', 'max:20', 'unique:employee_profiles,employee_number'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'address'         => ['nullable', 'string', 'max:500'],
            'iban'            => ['nullable', 'string', 'max:34'],
            'hourly_rate'     => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'contract_type'   => ['nullable', Rule::in(ContractTypeEnum::values())],
            'joined_at'       => ['nullable', 'date'],
            'notes'           => ['nullable', 'string'],
        ];
    }
}
