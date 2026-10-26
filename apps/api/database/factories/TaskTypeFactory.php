<?php

namespace Database\Factories;

use App\Enums\GoalTone;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskType>
 */
class TaskTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'tone' => fake()->randomElement(GoalTone::cases()),
        ];
    }
}
