<?php

namespace App\Modules\Planner\Database\Factories;

use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeBlock>
 */
class TimeBlockFactory extends Factory
{
    protected $model = TimeBlock::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'date' => today()->toDateString(),
            'start_minute' => 9 * 60,
            'minutes' => 60,
        ];
    }
}
