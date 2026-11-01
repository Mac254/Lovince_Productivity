<?php

namespace App\Modules\Blockers\Database\Factories;

use App\Modules\Blockers\Enums\BlockerStatus;
use App\Modules\Blockers\Models\Blocker;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Blocker>
 */
class BlockerFactory extends Factory
{
    protected $model = Blocker::class;

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
