<?php

namespace Database\Factories;

use App\Enums\FixFrequencyEnum;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\FixObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixObject>
 */
class FixObjectFactory extends Factory
{
    public function definition(): array
    {
        $customer = Customer::factory()->create();
        $location = CustomerLocation::factory()->create(['customer_id' => $customer->id]);

        return [
            'customer_id'    => $customer->id,
            'location_id'    => $location->id,
            'title'          => fake()->words(3, true) . ' Reinigung',
            'frequency'      => FixFrequencyEnum::Weekly,
            'frequency_days' => ['Mon', 'Wed', 'Fri'],
            'contract_hours' => 2.00,
            'time_start'     => '07:00:00',
            'time_end'       => '09:00:00',
            'valid_from'     => now()->startOfMonth()->toDateString(),
            'valid_until'    => null,
            'is_active'      => true,
            'calendar_color' => '#FFD700',
            'price_per_month'=> null,
            'price_per_hour' => null,
            'internal_cost'  => null,
            'profit_margin'  => null,
            'internal_notes' => null,
        ];
    }

    public function triweekly(): static
    {
        return $this->state(fn() => [
            'frequency'      => FixFrequencyEnum::Triweekly,
            'frequency_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn() => [
            'frequency'      => FixFrequencyEnum::Monthly,
            'frequency_days' => [],
        ]);
    }

    public function daily(): static
    {
        return $this->state(fn() => [
            'frequency'      => FixFrequencyEnum::Daily,
            'frequency_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        ]);
    }

    public function withFinancials(float $pricePerMonth = 500.00, float $cost = 300.00): static
    {
        return $this->state(fn() => [
            'price_per_month' => (string) $pricePerMonth,
            'internal_cost'   => (string) $cost,
            'profit_margin'   => (string) ($pricePerMonth - $cost),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['is_active' => false]);
    }

    public function withContractHours(float $hours): static
    {
        return $this->state(fn() => ['contract_hours' => $hours]);
    }
}
