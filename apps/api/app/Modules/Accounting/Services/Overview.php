<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\TransactionDirection;
use App\Modules\Accounting\Enums\TransactionStatus;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Accounting\Models\FinancialGoal;
use App\Modules\Accounting\Models\RecurringTransaction;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;

/**
 * The accounting dashboard for one currency and period: balances (in all, and split into
 * spending money and money set aside in savings and investments), the period's income,
 * spending and cash flow, a six-month trend, budgets, upcoming recurring payments, recent
 * transactions, progress on goals and rule-based insights. Each insight needs enough
 * transactions on both sides of a comparison before it says anything.
 */
final class Overview
{
    public const RECENT = 8;

    public const TREND_MONTHS = 6;

    /** Recurring payments due within this many days are listed as coming up. */
    public const UPCOMING_DAYS = 14;

    public const MIN_TRANSACTIONS = 3;

    /** The smallest change in spending worth mentioning, in percent. */
    public const NOTABLE_CHANGE = 10;

    /** The share of spending a single category needs before it is called out, in percent. */
    public const NOTABLE_SHARE = 30;

    public function __construct(private Ledger $ledger, private Statement $statement, private GoalProgress $progress) {}

    public static function currencyFor(User $user, ?string $requested): string
    {
        if ($requested !== null) {
            return $requested;
        }

        return FinancialAccount::query()->whereBelongsTo($user)->orderByRaw('CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END')->orderBy('id')->value('currency')
            ?? config('accounting.default_currency');
    }

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, Carbon $from, Carbon $to, string $currency): array
    {
        $accounts = $this->ledger->balances(
            FinancialAccount::query()->whereBelongsTo($user)->whereNull('archived_at')->where('currency', $currency)->orderBy('name')->get()
        );
        [$setAside, $spending] = $accounts->partition(fn (FinancialAccount $account) => $account->type->isSetAside());
        [$previousFrom, $previousTo, $monthly] = $this->previous($from, $to);

        $current = $this->totals($user, $from, $to, $currency);
        $previous = $this->totals($user, $previousFrom, $previousTo, $currency);

        $pending = Transaction::query()
            ->whereBelongsTo($user)
            ->where('currency', $currency)
            ->where('status', TransactionStatus::Pending)
            ->where('direction', TransactionDirection::Out)
            ->selectRaw('COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total')
            ->first();

        return [
            'currency' => $currency,
            'currencies' => FinancialAccount::query()->whereBelongsTo($user)->distinct()->orderBy('currency')->pluck('currency')->all(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'previous' => ['from' => $previousFrom->toDateString(), 'to' => $previousTo->toDateString(), 'monthly' => $monthly],
            'balance' => (int) $accounts->sum('balance'),
            'available_balance' => (int) $accounts->sum('available_balance'),
            'accounts_count' => $accounts->count(),
            'spending' => [
                'balance' => (int) $spending->sum('balance'),
                'available_balance' => (int) $spending->sum('available_balance'),
                'accounts_count' => $spending->count(),
            ],
            'set_aside' => [
                'balance' => (int) $setAside->sum('balance'),
                'accounts_count' => $setAside->count(),
            ],
            'income' => $current['income'],
            'expenses' => $current['expenses'],
            'net_cash_flow' => $current['net_cash_flow'],
            'previous_totals' => ['income' => $previous['income'], 'expenses' => $previous['expenses'], 'net_cash_flow' => $previous['net_cash_flow']],
            'top_expenses' => array_slice($current['categories'], 0, 5),
            'pending' => ['count' => (int) $pending->count, 'total' => (int) $pending->total],
            'recent' => Transaction::query()
                ->whereBelongsTo($user)
                ->where('currency', $currency)
                ->where('status', '!=', TransactionStatus::Voided)
                ->with(Transaction::RELATIONS)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit(self::RECENT)
                ->get(),
            'trend' => $this->statement->trend($user, $to->copy()->startOfMonth()->subMonthsNoOverflow(self::TREND_MONTHS - 1), $to, $currency),
            'budgets' => $budgets = $this->budgets($user, $from, $to, $currency),
            'upcoming' => RecurringTransaction::query()
                ->whereBelongsTo($user)
                ->where('currency', $currency)
                ->whereNull('paused_at')
                ->whereNotNull('next_date')
                ->whereDate('next_date', '<=', today()->addDays(self::UPCOMING_DAYS)->toDateString())
                ->with(RecurringTransaction::RELATIONS)
                ->orderBy('next_date')
                ->limit(5)
                ->get(),
            'goals' => $this->goals($user, $currency),
            'insights' => $this->insights($current, $previous, (int) $pending->count, (int) $pending->total, $budgets),
        ];
    }

    /**
     * The period's spending against the user's budgets, those nearest their limit first.
     *
     * @return array{amount: int, spent: int, over: int, items: list<array{id: int, category: string, amount: int, spent: int}>}
     */
    private function budgets(User $user, Carbon $from, Carbon $to, string $currency): array
    {
        $budgets = Budget::query()->whereBelongsTo($user)->where('currency', $currency)->with('category:id,name')->get();
        $spending = $budgets->isEmpty() ? [] : $this->statement->spending($user, $from, $to, $currency);
        // Budgets are monthly: a longer period gets one budget per calendar month it covers.
        $months = ($to->year - $from->year) * 12 + $to->month - $from->month + 1;
        $items = $budgets
            ->map(fn (Budget $budget) => [
                'id' => $budget->id,
                'category' => $budget->category->name,
                'amount' => $budget->amount * $months,
                'spent' => max(0, $spending[$budget->category_id] ?? 0),
            ])
            ->sortByDesc(fn (array $row) => $row['spent'] / $row['amount'])
            ->values();

        return [
            'amount' => (int) $items->sum('amount'),
            'spent' => (int) $items->sum('spent'),
            'over' => $items->filter(fn (array $row) => $row['spent'] > $row['amount'])->count(),
            'items' => $items->take(5)->all(),
        ];
    }

    /**
     * Open goals in the currency, those with the nearest target date first.
     *
     * @return array{count: int, saved: int, target: int, items: list<array{id: int, name: string, kind: string, saved: int, target_amount: int, target_date: string|null}>}
     */
    private function goals(User $user, string $currency): array
    {
        $goals = $this->progress->measure(
            FinancialGoal::query()->whereBelongsTo($user)->whereNull('archived_at')->where('currency', $currency)
                ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')->orderBy('target_date')->orderBy('name')->get()
        );

        return [
            'count' => $goals->count(),
            'saved' => (int) $goals->sum(fn (FinancialGoal $goal) => max(0, min($goal->saved, $goal->target_amount))),
            'target' => (int) $goals->sum('target_amount'),
            'items' => $goals->take(5)->map(fn (FinancialGoal $goal) => [
                'id' => $goal->id,
                'name' => $goal->name,
                'kind' => $goal->kind->value,
                'saved' => (int) $goal->saved,
                'target_amount' => $goal->target_amount,
                'target_date' => $goal->target_date?->toDateString(),
            ])->values()->all(),
        ];
    }

    /**
     * The period before: the previous calendar month when the range is one whole month,
     * otherwise the same number of days just before it.
     *
     * @return array{0: Carbon, 1: Carbon, 2: bool}
     */
    private function previous(Carbon $from, Carbon $to): array
    {
        $wholeMonth = $from->day === 1 && $to->isSameMonth($from) && $to->day === $to->daysInMonth;
        if ($wholeMonth) {
            $start = $from->copy()->subMonthNoOverflow()->startOfMonth();

            return [$start, $start->copy()->endOfMonth()->startOfDay(), true];
        }

        $days = (int) $from->diffInDays($to) + 1;

        return [$from->copy()->subDays($days), $from->copy()->subDay(), false];
    }

    /**
     * @return array{income: int, expenses: int, net_cash_flow: int, categories: list<array{id: int|null, name: string, amount: int}>, counts: array{income: int, expense: int}}
     */
    private function totals(User $user, Carbon $from, Carbon $to, string $currency): array
    {
        $statement = $this->statement->incomeStatement($user, $from, $to, $currency);
        $cash = $this->statement->cashFlow($user, $from, $to, $currency);

        return [
            'income' => $statement['income']['total'],
            'expenses' => $statement['expenses']['total'],
            'net_cash_flow' => $cash['net'],
            'categories' => $statement['expenses']['categories'],
            'counts' => $this->statement->counts($user, $from, $to, $currency),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @param  array{amount: int, spent: int, over: int, items: list<array<string, mixed>>}  $budgets
     * @return list<array<string, mixed>>
     */
    private function insights(array $current, array $previous, int $pendingCount, int $pendingTotal, array $budgets): array
    {
        $insights = [];

        if ($budgets['over'] > 0) {
            $over = array_values(array_filter($budgets['items'], fn (array $row) => $row['spent'] > $row['amount']));
            $insights[] = ['key' => 'over_budget', 'tone' => 'warning', 'count' => $budgets['over'], 'category' => $over[0]['category'] ?? null, 'amount' => array_sum(array_map(fn ($row) => $row['spent'] - $row['amount'], $over))];
        }

        $enough = $current['counts']['expense'] >= self::MIN_TRANSACTIONS && $previous['counts']['expense'] >= self::MIN_TRANSACTIONS;
        if ($enough && $previous['expenses'] > 0) {
            $change = (int) round(($current['expenses'] - $previous['expenses']) * 100 / $previous['expenses']);
            if (abs($change) >= self::NOTABLE_CHANGE) {
                $insights[] = ['key' => 'spending_change', 'tone' => $change > 0 ? 'warning' : 'success', 'percent' => $change, 'amount' => $current['expenses'] - $previous['expenses']];
            }
        }

        $top = $current['categories'][0] ?? null;
        if ($top !== null && $current['counts']['expense'] >= self::MIN_TRANSACTIONS && $current['expenses'] > 0 && count($current['categories']) > 1) {
            $share = (int) round($top['amount'] * 100 / $current['expenses']);
            if ($share >= self::NOTABLE_SHARE) {
                $insights[] = ['key' => 'top_category', 'tone' => 'info', 'percent' => $share, 'amount' => $top['amount'], 'category' => $top['name']];
            }
        }

        if ($current['net_cash_flow'] < 0 && $current['counts']['expense'] >= self::MIN_TRANSACTIONS) {
            $insights[] = ['key' => 'negative_cash_flow', 'tone' => 'warning', 'amount' => -$current['net_cash_flow']];
        }

        if ($pendingCount > 0) {
            $insights[] = ['key' => 'pending', 'tone' => 'info', 'count' => $pendingCount, 'amount' => $pendingTotal];
        }

        return $insights;
    }
}
