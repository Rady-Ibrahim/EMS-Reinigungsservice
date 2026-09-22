<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerLocation>
 */
class CustomerLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id'         => Customer::factory(),
            'name'                => fake()->streetName() . ' Objekt',
            'street'              => fake()->streetName(),
            'house_number'        => fake()->buildingNumber(),
            'postal_code'         => fake()->postcode(),
            'city'                => fake()->city(),
            'country'             => 'DE',
            'latitude'            => fake()->latitude(47, 55),
            'longitude'           => fake()->longitude(6, 15),
            'contact_person'      => fake()->name(),
            'contact_phone'       => fake()->phoneNumber(),
            'access_instructions' => fake()->sentence(),
            'security_code'       => null,
            'service_checklist'   => ['Boden reinigen', 'Fenster putzen', 'Müll entsorgen'],
            'working_days'        => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'working_hours_start' => '07:00:00',
            'working_hours_end'   => '16:00:00',
            'is_active'           => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['is_active' => false]);
    }

    public function withSecurityCode(): static
    {
        return $this->state(fn() => ['security_code' => 'CODE-' . fake()->numerify('####')]);
    }
}
