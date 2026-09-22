<?php

namespace Database\Factories;

use App\Enums\ContractTypeEnum;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'         => User::factory()->mitarbeiter(),
            'calendar_color'  => fake()->hexColor(),
            'employee_number' => fake()->unique()->numerify('EMP-####'),
            'phone'           => fake()->phoneNumber(),
            'address'         => fake()->address(),
            'iban'            => null,    // sensitive — set explicitly in tests
            'hourly_rate'     => null,    // sensitive — set explicitly in tests
            'contract_type'   => ContractTypeEnum::Minijob,
            'joined_at'       => fake()->dateTimeBetween('-3 years', 'now'),
            'notes'           => null,
        ];
    }

    public function withHourlyRate(float $rate = 12.50): static
    {
        return $this->state(fn() => ['hourly_rate' => (string) $rate]);
    }

    public function withIban(string $iban = 'DE89370400440532013000'): static
    {
        return $this->state(fn() => ['iban' => $iban]);
    }

    public function vollzeit(): static
    {
        return $this->state(fn() => ['contract_type' => ContractTypeEnum::Vollzeit]);
    }
}
