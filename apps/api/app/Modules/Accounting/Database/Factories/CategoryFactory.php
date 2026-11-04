<?php

namespace App\Modules\Accounting\Database\Factories;

use App\Modules\Accounting\Enums\CategoryKind;
use App\Modules\Accounting\Models\Category;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => CategoryKind::Expense,
            'name' => fake()->unique()->word(),
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => ['kind' => CategoryKind::Income]);
    }
}
