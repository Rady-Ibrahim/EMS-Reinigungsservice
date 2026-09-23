<?php

namespace Database\Factories;

use App\Enums\ShiftStatusEnum;
use App\Models\EmployeeShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeShift>
 */
class EmployeeShiftFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+14 days');

        return [
            'user_id'    => User::factory()->mitarbeiter(),
            'created_by' => User::factory()->administrator(),
            'title'      => fake()->randomElement(['Frühschicht', 'Spätschicht', 'Büro', 'Dienst']),
            'start_at'   => $start,
            'end_at'     => (clone $start)->modify('+8 hours'),
            'all_day'    => false,
            'color'      => '#10b981',
            'notes'      => null,
            'status'     => ShiftStatusEnum::Planned,
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

    public function cancelled(): static
    {
        return $this->state(fn() => ['status' => ShiftStatusEnum::Cancelled]);
    }
}