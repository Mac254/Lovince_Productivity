<?php

namespace Database\Factories;

use App\Enums\BlockerStatus;
use App\Models\Blocker;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Blocker>
 */
class BlockerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'description' => fake()->sentence(8),
            'status' => BlockerStatus::Open,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => BlockerStatus::Resolved]);
    }
}
