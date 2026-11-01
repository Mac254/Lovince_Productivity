<?php

namespace App\Modules\Tasks\Database\Factories;

use App\Modules\Tasks\Enums\TaskCategory;
use App\Modules\Tasks\Enums\TaskPriority;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(5),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::NotStarted,
            'priority' => TaskPriority::Medium,
            'category' => TaskCategory::General,
            'due_date' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => TaskStatus::Completed]);
    }

    public function dueOn(string $date): static
    {
        return $this->state(fn () => ['due_date' => $date]);
    }
}
