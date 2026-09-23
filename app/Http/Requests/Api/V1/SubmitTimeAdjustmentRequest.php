<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitTimeAdjustmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'job_type'            => ['required', Rule::in(['fix_object', 'extra_auftrag'])],
            'job_id'              => ['required', 'integer', 'min:1'],
            'requested_start'     => ['required', 'date', 'before:requested_end'],
            'requested_end'       => ['required', 'date', 'after:requested_start'],
            'reason'              => ['required', 'string', 'min:10', 'max:1000'],
            'offline_uuid'        => ['nullable', 'uuid'],
            'client_submitted_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.min' => 'Bitte geben Sie einen ausführlicheren Grund an (min. 10 Zeichen).',
        ];
    }
}
