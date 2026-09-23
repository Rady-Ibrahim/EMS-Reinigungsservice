<?php

namespace App\Http\Requests\Admin\FixObject;

use App\Enums\FixFrequencyEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFixObjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'     => ['required', 'exists:customers,id'],
            'location_id'     => ['required', 'exists:customer_locations,id'],
            'title'           => ['required', 'string', 'max:255'],
            'frequency'       => ['required', Rule::in(FixFrequencyEnum::values())],
            'frequency_days'  => ['nullable', 'array'],
            'frequency_days.*'=> ['string', 'in:Mon,Tue,Wed,Thu,Fri,Sat,Sun'],
            'contract_hours'  => ['required', 'numeric', 'min:0.25', 'max:24'],
            'time_start'      => ['nullable', 'date_format:H:i'],
            'time_end'        => ['nullable', 'date_format:H:i', 'after:time_start'],
            'valid_from'      => ['required', 'date'],
            'valid_until'     => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'       => ['boolean'],
            'calendar_color'  => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // Financial — Admin only, optional
            'price_per_month' => ['nullable', 'numeric', 'min:0'],
            'price_per_hour'  => ['nullable', 'numeric', 'min:0'],
            'internal_cost'   => ['nullable', 'numeric', 'min:0'],
            'profit_margin'   => ['nullable', 'numeric'],
            'internal_notes'  => ['nullable', 'string'],
        ];
    }
}
