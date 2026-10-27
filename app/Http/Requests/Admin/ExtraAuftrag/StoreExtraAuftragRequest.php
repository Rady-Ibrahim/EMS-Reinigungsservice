<?php

namespace App\Http\Requests\Admin\ExtraAuftrag;

use App\Enums\AssigneeRoleEnum;
use App\Enums\ExtraOrderTypeEnum;
use App\Enums\FileCategoryEnum;
use App\Http\Requests\Admin\ExtraAuftrag\Concerns\HasCompanyAssigneeValidation;
use App\Services\DocumentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExtraAuftragRequest extends FormRequest
{
    use HasCompanyAssigneeValidation;

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
            'company_id'           => ['nullable', 'integer', 'exists:companies,id'],
            'checklist_template'   => ['nullable', 'array'],
            'checklist_template.*.task' => ['required_with:checklist_template', 'string', 'max:255'],
            'price'                => ['nullable', 'numeric', 'min:0'],
            'price_per_hour'       => ['nullable', 'numeric', 'min:0'],
            'profit_margin'        => ['nullable', 'numeric'],
            'profit_margin_type'   => ['nullable', 'in:eur,pct'],
            'internal_cost'        => ['nullable', 'numeric', 'min:0'],
            'company_payout'       => ['nullable', 'numeric', 'min:0'],
            'internal_notes'       => ['nullable', 'string'],
            'attachment'           => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,heic,doc,docx'],
            // Intern (admin-only) document section
            'intern_document'          => ['nullable', 'file', 'max:'.DocumentService::MAX_KILOBYTES, 'mimetypes:'.implode(',', DocumentService::ALLOWED_MIME)],
            'intern_document_title'    => ['nullable', 'string', 'max:191'],
            'intern_document_category' => ['nullable', 'in:'.implode(',', FileCategoryEnum::values())],
            'intern_document_notes'    => ['nullable', 'string', 'max:2000'],
            // Extern (team-visible) document section
            'extern_document'          => ['nullable', 'file', 'max:'.DocumentService::MAX_KILOBYTES, 'mimetypes:'.implode(',', DocumentService::ALLOWED_MIME)],
            'extern_document_title'    => ['nullable', 'string', 'max:191'],
            'extern_document_category' => ['nullable', 'in:'.implode(',', FileCategoryEnum::values())],
            'extern_document_notes'    => ['nullable', 'string', 'max:2000'],
            // Assignees — at least one leader required (enforced in service)
            'assignees'            => ['required', 'array', 'min:1'],
            'assignees.*.user_id'  => ['required', $this->companyAssigneeRule()],
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
