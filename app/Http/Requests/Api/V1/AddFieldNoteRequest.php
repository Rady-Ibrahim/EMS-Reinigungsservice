<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/extra-orders/{id}/notes
 *
 * Quick field note appended by the assigned employee. Kept short on purpose:
 * this is a note taken on the phone at the site, not a report.
 */
class AddFieldNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:2000'],
        ];
    }
}
