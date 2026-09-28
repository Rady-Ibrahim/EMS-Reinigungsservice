<?php

namespace Database\Factories;

use App\Enums\ComplaintStatusEnum;
use App\Models\Complaint;
use App\Models\CustomerLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id'          => null,
            'location_id'          => CustomerLocation::factory(),
            'assigned_employee_id' => null,
            'reported_by'          => fake()->name(),
            'occurred_at'          => fake()->dateTimeBetween('-14 days', 'now'),
            'title'                => fake()->randomElement([
                'Fenster streifig gereinigt',
                'Sanitärbereich unvollständig',
                'Boden nicht nachgewischt',
                'Müll nicht entsorgt',
            ]),
            'description'          => fake()->paragraph(),
            'photos'               => [],
            'status'               => ComplaintStatusEnum::Pending->value,
            'resolution'           => null,
            'resolved_at'          => null,
            'resolved_by'          => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn() => ['status' => ComplaintStatusEnum::Pending->value]);
    }

    public function inProgress(): static
    {
        return $this->state(fn() => ['status' => ComplaintStatusEnum::InProgress->value]);
    }

    public function resolved(): static
    {
        return $this->state(fn() => [
            'status'      => ComplaintStatusEnum::Resolved->value,
            'resolution'  => fake()->sentence(),
            'resolved_at' => now(),
            'resolved_by' => User::factory()->administrator(),
        ]);
    }

    public function assignedTo(User|int $employee): static
    {
        return $this->state(fn() => [
            'assigned_employee_id' => $employee instanceof User ? $employee->id : $employee,
        ]);
    }

    public function withPhotos(): static
    {
        return $this->state(fn() => [
            'photos' => ['complaints/'.fake()->uuid().'.jpg', 'complaints/'.fake()->uuid().'.jpg'],
        ]);
    }

    public function withoutPhotos(): static
    {
        return $this->state(fn() => ['photos' => []]);
    }
}
