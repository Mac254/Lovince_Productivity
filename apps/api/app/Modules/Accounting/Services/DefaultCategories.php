<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\CategoryKind;
use App\Modules\Accounting\Models\Category;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gives each user the starting categories the first time they open the module. Defaults can be
 * archived but not deleted, so having none means the user has never been set up.
 */
final class DefaultCategories
{
    public const INCOME = ['Salary', 'Business', 'Gifts', 'Interest', 'Other income'];

    /** Savings is left out on purpose: money put aside is a transfer to a savings account, not spending. */
    public const EXPENSE = ['Housing', 'Food', 'Transport', 'Healthcare', 'Education', 'Entertainment', 'Communication', 'Financial', 'Personal'];

    public static function ensure(User $user): void
    {
        if (Category::query()->whereBelongsTo($user)->exists()) {
            return;
        }

        DB::transaction(function () use ($user) {
            $now = now();
            $rows = [];
            foreach ([CategoryKind::Income->value => self::INCOME, CategoryKind::Expense->value => self::EXPENSE] as $kind => $names) {
                foreach ($names as $name) {
                    $rows[] = ['user_id' => $user->id, 'kind' => $kind, 'name' => $name, 'is_default' => true, 'created_at' => $now, 'updated_at' => $now];
                }
            }
            Category::query()->insertOrIgnore($rows);
        });
    }
}
