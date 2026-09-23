<?php

namespace App\Http\Requests\Admin\ExtraAuftrag;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraOrderTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExtraAuftragRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'          => ['required', 'exists:customers,id'],
            'location_id'          => ['required', 'exists:customer_locations,id'],
            'title'                => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'order_type'           => ['required', Rule::in(ExtraOrderTypeEnum::values())],
            'scheduled_date'       => ['required', 'date'],
            'scheduled_time_start' => ['nullable', 'date_format:H:i'],
            'estimated_hours'      => ['nullable', 'numeric', 'min:0.25', 'max:24'],
            'is_travel_time_paid'  => ['boolean'],
            'checklist_template'   => ['nullable', 'array'],
            'checklist_template.*.task' => ['required_with:checklist_template', 'string', 'max:255'],
            'price'                => ['nullable', 'numeric', 'min:0'],
            'internal_cost'        => ['nullable', 'numeric', 'min:0'],
            'internal_notes'       => ['nullable', 'string'],
            // Assignees — at least one leader required (enforced in service)
            'assignees'            => ['required', 'array', 'min:1'],
            'assignees.*.user_id'  => ['required', 'exists:users,id'],
            'assignees.*.role_in_order' => ['required', Rule::in(AssigneeRoleEnum::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'assignees.required' => 'Mindestens ein Mitarbeiter muss zugewiesen werden.',
            'assignees.min'      => 'Mindestens ein Mitarbeiter muss zugewiesen werden.',
        ];
    }
}
