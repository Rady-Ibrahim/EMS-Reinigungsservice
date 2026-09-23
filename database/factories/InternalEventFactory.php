<?php

namespace Database\Factories;

use App\Enums\InternalEventTypeEnum;
use App\Models\InternalEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalEvent>
 */
class InternalEventFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+14 days');

        return [
            'created_by'  => User::factory()->administrator(),
            'title'       => fake()->randomElement(['Objektbegehung', 'Team-Besprechung', 'Rückruf', 'Erinnerung']),
            'event_type'  => fake()->randomElement(InternalEventTypeEnum::values()),
            'start_at'    => $start,
            'end_at'      => (clone $start)->modify('+2 hours'),
            'all_day'     => false,
            'location'    => fake()->company(),
            'description' => fake()->sentence(),
            'color'       => '#f59e0b',
            'reminders'   => [],
        ];
    }

    public function allDay(): static
    {
        $day = fake()->dateTimeBetween('now', '+14 days')->format('Y-m-d');

        return $this->state(fn() => [
            'all_day'  => true,
            'start_at' => $day.' 00:00:00',
            'end_at'   => $day.' 23:59:59',
        ]);
    }
}