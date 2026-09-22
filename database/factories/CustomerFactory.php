<?php

namespace Database\Factories;

use App\Enums\CustomerStatusEnum;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'           => fake()->company(),
            'email'          => fake()->unique()->companyEmail(),
            'phone'          => fake()->phoneNumber(),
            'contact_person' => fake()->name(),
            'status'         => CustomerStatusEnum::Active,
            'notes'          => null,
            'portal_access'  => false,
            'portal_email'   => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['status' => CustomerStatusEnum::Inactive]);
    }

    public function withPortal(): static
    {
        return $this->state(fn() => [
            'portal_access' => true,
            'portal_email'  => fake()->email(),
        ]);
    }
}
