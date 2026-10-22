<?php

namespace App\Http\Requests\Admin\FixObject\Concerns;

use App\Enums\FixFrequencyEnum;
use Closure;
use Illuminate\Validation\Rule;

trait HasFrequencyDayValidation
{
    protected function frequencyDayRules(): array
    {
        $frequency = FixFrequencyEnum::tryFrom((string) $this->input('frequency'));
        $requiredCount = $frequency?->requiredWeekdayCount();

        return [
            Rule::requiredIf($requiredCount !== null),
            'array',
            function (string $attribute, mixed $value, Closure $fail) use ($requiredCount): void {
                if ($requiredCount !== null && is_array($value) && $value !== [] && count($value) !== $requiredCount) {
                    $fail(__('fix_object.weekdays_count', ['count' => $requiredCount]));
                }
            },
        ];
    }
}
