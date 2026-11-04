<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeAvailabilityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'start_at' => ['bail', 'required', 'date', 'after:now'],
            'end_at' => [
                'bail',
                'required',
                'date',
                'after:start_at',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $start = $this->input('start_at');

                    if (! is_scalar($start) || ! is_scalar($value)) {
                        return;
                    }

                    $startTimestamp = strtotime((string) $start);
                    $endTimestamp = strtotime((string) $value);

                    if ($startTimestamp === false || $endTimestamp === false) {
                        return;
                    }

                    $minutes = ($endTimestamp - $startTimestamp) / 60;

                    if ($minutes < 15 || $minutes > 1440) {
                        $fail(__('availability.duration_invalid'));
                    }
                },
            ],
            'address' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
