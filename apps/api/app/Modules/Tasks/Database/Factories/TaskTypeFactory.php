<?php

namespace App\Modules\Tasks\Database\Factories;

use App\Modules\Goals\Enums\GoalTone;
use App\Modules\Tasks\Models\TaskType;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskType>
 */
class TaskTypeFactory extends Factory
{
    protected $model = TaskType::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'tone' => fake()->randomElement(GoalTone::cases()),
        ];
    }
}
