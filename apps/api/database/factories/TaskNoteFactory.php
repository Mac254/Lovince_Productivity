<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskNote>
 */
class TaskNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => fn (array $attributes) => Task::find($attributes['task_id'])->user_id,
            'logged_on' => today()->toDateString(),
            'body' => fake()->sentence(),
            'minutes' => fake()->optional()->numberBetween(5, 240),
        ];
    }
}
