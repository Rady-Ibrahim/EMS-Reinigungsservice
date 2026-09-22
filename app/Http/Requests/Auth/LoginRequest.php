<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => __('validation.required', ['attribute' => __('validation.attributes.email')]),
            'email.email'       => __('validation.email',    ['attribute' => __('validation.attributes.email')]),
            'password.required' => __('validation.required', ['attribute' => __('validation.attributes.password')]),
            'password.min'      => __('validation.min.string', ['attribute' => __('validation.attributes.password'), 'min' => 6]),
        ];
    }
}
