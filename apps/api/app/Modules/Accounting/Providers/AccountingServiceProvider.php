<?php

namespace App\Modules\Accounting\Providers;

use App\Modules\Accounting\Console\Commands\PostRecurring;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\Category;
use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Accounting\Models\FinancialGoal;
use App\Modules\Accounting\Models\RecurringTransaction;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Policies\BudgetPolicy;
use App\Modules\Accounting\Policies\CategoryPolicy;
use App\Modules\Accounting\Policies\FinancialAccountPolicy;
use App\Modules\Accounting\Policies\FinancialGoalPolicy;
use App\Modules\Accounting\Policies\RecurringTransactionPolicy;
use App\Modules\Accounting\Policies\TransactionPolicy;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule;

class AccountingServiceProvider extends ModuleProvider
{
    protected array $policies = [
        FinancialAccount::class => FinancialAccountPolicy::class,
        Category::class => CategoryPolicy::class,
        Transaction::class => TransactionPolicy::class,
        Budget::class => BudgetPolicy::class,
        RecurringTransaction::class => RecurringTransactionPolicy::class,
        FinancialGoal::class => FinancialGoalPolicy::class,
    ];

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('financialAccounts', fn (User $user) => $user->hasMany(FinancialAccount::class));
        User::resolveRelationUsing('accountingCategories', fn (User $user) => $user->hasMany(Category::class));
        User::resolveRelationUsing('accountingBudgets', fn (User $user) => $user->hasMany(Budget::class));
        User::resolveRelationUsing('accountingGoals', fn (User $user) => $user->hasMany(FinancialGoal::class));

        $this->commands([PostRecurring::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // Due payments are recorded within the quarter hour after each owner's midnight; missed days catch up.
            $schedule->command('accounting:post-recurring')->everyFifteenMinutes()->withoutOverlapping();
        });
    }
}
