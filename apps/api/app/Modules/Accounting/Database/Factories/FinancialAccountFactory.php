<?php

namespace App\Modules\Accounting\Database\Factories;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialAccount>
 */
class FinancialAccountFactory extends Factory
{
    protected $model = FinancialAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'type' => AccountType::Bank,
            'currency' => 'KES',
            'opening_balance' => 0,
        ];
    }
}
