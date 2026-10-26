<?php

namespace Database\Factories;

use App\Enums\GoalStatus;
use App\Enums\GoalTimeframe;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
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
