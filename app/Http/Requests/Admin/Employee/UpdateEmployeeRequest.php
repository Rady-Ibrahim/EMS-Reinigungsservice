<?php

namespace App\Http\Requests\Admin\Employee;

use App\Enums\ContractTypeEnum;
use App\Enums\RoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('employee')->id ?? null;

        return [
            // User fields
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', Password::min(8)->mixedCase()->numbers()],
            'role'     => ['required', Rule::in([RoleEnum::Vorarbeiter->value, RoleEnum::Mitarbeiter->value])],
            'locale'   => ['required', 'in:de,ar,en'],
            'is_active'=> ['boolean'],

            // Profile fields
            'calendar_color'  => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'employee_number' => [
                'nullable', 'string', 'max:20',
                Rule::unique('employee_profiles', 'employee_number')
                    ->where(fn($q) => $q->where('user_id', '!=', $userId)),
            ],
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
