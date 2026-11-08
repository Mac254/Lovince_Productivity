<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\FinancialGoal;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How far each goal has come. A goal linked to an account has saved that account's ledger
 * balance; any other goal has saved the sum of its contributions.
 */
final class GoalProgress
{
    public function __construct(private Ledger $ledger) {}

    /**
     * Sets `saved` on each goal.
     *
     * @param  Collection<int, FinancialGoal>  $goals
     * @return Collection<int, FinancialGoal>
     */
    public function measure(Collection $goals): Collection
    {
        (new EloquentCollection($goals->all()))->loadMissing('account');
        $accounts = $this->ledger->balances($goals->pluck('account')->filter()->unique('id')->values())->keyBy('id');
        $contributed = $goals->whereNull('account_id')->isEmpty() ? collect() : FinancialGoal::query()
            ->whereKey($goals->whereNull('account_id')->pluck('id'))
            ->withSum('contributions as total', 'amount')
            ->pluck('total', 'id');

        return $goals->each(fn (FinancialGoal $goal) => $goal->saved = $goal->account_id !== null
            ? (int) ($accounts[$goal->account_id]->balance ?? 0)
            : (int) ($contributed[$goal->id] ?? 0));
    }

    /**
     * What is left to save each month to reach the target by its date, counting the current
     * month as one; null without a date or once the goal is reached.
     */
    public static function monthlyNeeded(FinancialGoal $goal, ?Carbon $today = null): ?int
    {
        $remaining = $goal->target_amount - (int) $goal->saved;
        if ($goal->target_date === null || $remaining <= 0) {
            return null;
        }

        $today ??= today();
        $months = ($goal->target_date->year - $today->year) * 12 + $goal->target_date->month - $today->month + 1;

        return (int) ceil($remaining / max(1, $months));
    }
}
