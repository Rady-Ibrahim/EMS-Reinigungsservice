<?php

namespace Database\Factories;

use App\Models\InternalEvent;
use App\Models\InternalEventAssignee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalEventAssignee>
 */
class InternalEventAssigneeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'internal_event_id' => InternalEvent::factory(),
            'user_id'           => User::factory()->mitarbeiter(),
        ];
    }
}