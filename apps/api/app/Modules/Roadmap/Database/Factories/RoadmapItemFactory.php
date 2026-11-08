<?php

namespace App\Modules\Roadmap\Database\Factories;

use App\Modules\Roadmap\Models\RoadmapItem;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RoadmapItem> */
class RoadmapItemFactory extends Factory
{
    protected $model = RoadmapItem::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'stage' => 'next',
        ];
    }
}
