<?php

namespace App\Http\Requests\Admin\ExtraAuftrag;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraOrderTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExtraAuftragRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
            'assignees'            => ['nullable', 'array'],
            'assignees.*.user_id'  => ['required_with:assignees', 'exists:users,id'],
            'assignees.*.role_in_order' => ['required_with:assignees', Rule::in(AssigneeRoleEnum::values())],
        ];
    }
}
