<?php

namespace Database\Factories;

use App\Enums\ExtraAuftragStatusEnum;
use App\Enums\ExtraOrderTypeEnum;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\ExtraAuftrag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtraAuftrag>
 */
class ExtraAuftragFactory extends Factory
{
    public function definition(): array
    {
        $customer = Customer::factory()->create();
        $location = CustomerLocation::factory()->create(['customer_id' => $customer->id]);
        $creator  = User::factory()->administrator()->create();

        return [
            'customer_id'          => $customer->id,
            'location_id'          => $location->id,
            'created_by'           => $creator->id,
            'title'                => fake()->words(3, true),
            'description'          => fake()->sentence(),
            'order_type'           => ExtraOrderTypeEnum::Other,
            'scheduled_date'       => now()->addDays(3)->toDateString(),
            'scheduled_time_start' => '08:00:00',
            'estimated_hours'      => 3.0,
            'is_travel_time_paid'  => false,
            'status'               => ExtraAuftragStatusEnum::Pending,
            'checklist_template'   => [
                ['task' => 'Boden reinigen',   'completed' => false],
                ['task' => 'Fenster putzen',   'completed' => false],
                ['task' => 'Müll entsorgen',   'completed' => false],
            ],
            'price'          => null,
            'internal_cost'  => null,
            'internal_notes' => null,
        ];
    }

    public function withTravelTimePaid(): static
    {
        return $this->state(fn() => ['is_travel_time_paid' => true]);
    }

    public function withFinancials(float $price = 600.00, float $cost = 350.00): static
    {
        return $this->state(fn() => [
            'price'         => (string) $price,
            'internal_cost' => (string) $cost,
        ]);
    }

    public function assigned(): static
    {
        return $this->state(fn() => ['status' => ExtraAuftragStatusEnum::Assigned]);
    }

    public function completed(): static
    {
        return $this->state(fn() => ['status' => ExtraAuftragStatusEnum::Completed]);
    }

    public function withEmptyChecklist(): static
    {
        return $this->state(fn() => ['checklist_template' => []]);
    }
}
