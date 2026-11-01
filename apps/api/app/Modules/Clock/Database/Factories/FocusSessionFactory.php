<?php

namespace App\Modules\Clock\Database\Factories;

use App\Modules\Clock\Models\FocusSession;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FocusSession>
 */
class FocusSessionFactory extends Factory
{
    protected $model = FocusSession::class;

    public function definition(): array
    {
        $start = now()->subHour();

        return [
            'user_id' => User::factory(),
            'started_on' => $start->toDateString(),
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes(25),
            'planned_minutes' => 25,
            'focused_seconds' => 1500,
            'completed' => true,
        ];
    }
}
