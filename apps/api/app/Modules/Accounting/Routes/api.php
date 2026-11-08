<?php

use App\Modules\Accounting\Http\Controllers\AccountController;
use App\Modules\Accounting\Http\Controllers\BudgetController;
use App\Modules\Accounting\Http\Controllers\CategoryController;
use App\Modules\Accounting\Http\Controllers\DashboardController;
use App\Modules\Accounting\Http\Controllers\GoalContributionController;
use App\Modules\Accounting\Http\Controllers\GoalController;
use App\Modules\Accounting\Http\Controllers\RecurringTransactionController;
use App\Modules\Accounting\Http\Controllers\ReportController;
use App\Modules\Accounting\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('accounting')->name('accounting.')->group(function () {
    Route::get('dashboard', DashboardController::class);
    Route::get('reports/income-statement', [ReportController::class, 'incomeStatement']);
    Route::get('reports/cash-flow', [ReportController::class, 'cashFlow']);
    Route::get('reports/trend', [ReportController::class, 'trend']);
    Route::apiResource('accounts', AccountController::class)->whereNumber('account');
    Route::apiResource('categories', CategoryController::class)->except('show')->whereNumber('category');
    Route::apiResource('budgets', BudgetController::class)->except('show')->whereNumber('budget');
    Route::apiResource('goals', GoalController::class)->whereNumber('goal');
    Route::apiResource('goals.contributions', GoalContributionController::class)->only(['index', 'store', 'destroy'])->scoped()->whereNumber(['goal', 'contribution']);
    Route::apiResource('recurring', RecurringTransactionController::class)->except('show')->parameters(['recurring' => 'recurring'])->whereNumber('recurring');
    Route::get('transactions/export', [TransactionController::class, 'export'])->middleware('throttle:10,1');
    Route::post('transactions/{transaction}/void', [TransactionController::class, 'void'])->whereNumber('transaction');
    Route::post('transactions/{transaction}/reverse', [TransactionController::class, 'reverse'])->whereNumber('transaction');
    Route::apiResource('transactions', TransactionController::class)->except('destroy')->whereNumber('transaction');
});
