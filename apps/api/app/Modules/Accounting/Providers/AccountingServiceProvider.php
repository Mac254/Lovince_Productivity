<?php

namespace App\Modules\Accounting\Providers;

use App\Modules\Accounting\Models\Category;
use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Policies\CategoryPolicy;
use App\Modules\Accounting\Policies\FinancialAccountPolicy;
use App\Modules\Accounting\Policies\TransactionPolicy;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class AccountingServiceProvider extends ModuleProvider
{
    protected array $policies = [
        FinancialAccount::class => FinancialAccountPolicy::class,
        Category::class => CategoryPolicy::class,
        Transaction::class => TransactionPolicy::class,
    ];

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('financialAccounts', fn (User $user) => $user->hasMany(FinancialAccount::class));
        User::resolveRelationUsing('accountingCategories', fn (User $user) => $user->hasMany(Category::class));
    }
}
