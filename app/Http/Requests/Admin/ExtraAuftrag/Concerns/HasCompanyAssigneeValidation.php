<?php

namespace App\Http\Requests\Admin\ExtraAuftrag\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait HasCompanyAssigneeValidation
{
    protected function companyAssigneeRule(): Exists
    {
        $companyId = $this->input('company_id');

        return Rule::exists('users', 'id')->where(function ($query) use ($companyId): void {
            $query->whereIn('role', ['vorarbeiter', 'mitarbeiter'])
                ->where('is_active', true);

            if ($companyId !== null && $companyId !== '') {
                $query->where('company_id', $companyId);
            }
        });
    }
}
