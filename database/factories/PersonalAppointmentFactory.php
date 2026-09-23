<?php

namespace Database\Factories;

use App\Models\PersonalAppointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalAppointment>
 */
class PersonalAppointmentFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+14 days');

        return [
            'user_id'     => User::factory()->mitarbeiter(),
            'title'       => fake()->randomElement(['Arzt', 'Zahnarzt', 'Privat', 'Umzug']),
            'start_at'    => $start,
            'end_at'      => (clone $start)->modify('+1 hour'),
            'all_day'     => false,
            'description' => fake()->sentence(),
            'location'    => fake()->city(),
            'color'       => '#8b5cf6',
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