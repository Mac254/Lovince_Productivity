<?php

namespace App\Modules\Tasks\Database\Factories;

use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskNote>
 */
class TaskNoteFactory extends Factory
{
    protected $model = TaskNote::class;

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
