<?php

namespace Database\Factories;

use App\Enums\ScheduleStatusEnum;
use App\Models\FixObject;
use App\Models\FixObjectSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixObjectSchedule>
 */
class FixObjectScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fix_object_id'   => FixObject::factory(),
            'scheduled_date'  => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'scheduled_start' => '07:00:00',
            'scheduled_end'   => '09:00:00',
            'status'          => ScheduleStatusEnum::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn() => ['status' => ScheduleStatusEnum::Completed]);
    }

    public function forDate(string $date): static
    {
        return $this->state(fn() => ['scheduled_date' => $date]);
    }
}
