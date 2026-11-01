<?php

namespace App\Modules\Goals\Database\Factories;

use App\Modules\Goals\Enums\GoalStatus;
use App\Modules\Goals\Enums\GoalTimeframe;
use App\Modules\Goals\Models\Goal;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => GoalStatus::Active,
            'timeframe' => GoalTimeframe::LongTerm,
            'start_date' => today()->toDateString(),
            'target_date' => today()->addMonth()->toDateString(),
        ];
    }

    public function weekly(): static
    {
        return $this->state(fn () => [
            'timeframe' => GoalTimeframe::Weekly,
            'start_date' => today()->startOfWeek()->toDateString(),
            'target_date' => today()->endOfWeek()->toDateString(),
        ]);
    }
}
