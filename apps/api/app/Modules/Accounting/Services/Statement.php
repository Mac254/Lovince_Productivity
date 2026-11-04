<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\LineKind;
use App\Modules\Accounting\Enums\TransactionType;
use App\Modules\Accounting\Models\Category;
use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Users\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Income statement and cash flow for one currency over a date range, read from journal entries
 * still in force. Transfers move money between the user's own accounts, so they appear per
 * account but never in consolidated income, spending or cash flow. Adjustments correct a
 * balance without being earned or spent, so cash flow shows them apart.
 */
final class Statement
{
    /**
     * @return array<string, mixed>
     */
    public function incomeStatement(User $user, Carbon $from, Carbon $to, string $currency): array
    {
        $income = $this->byCategory($user, $from, $to, $currency, LineKind::Income);
        $expenses = $this->byCategory($user, $from, $to, $currency, LineKind::Expense);
        $incomeTotal = array_sum(array_column($income, 'amount'));
        $expenseTotal = array_sum(array_column($expenses, 'amount'));

        return [
            'income' => ['total' => $incomeTotal, 'categories' => $income],
            'expenses' => ['total' => $expenseTotal, 'categories' => $expenses],
            'net' => $incomeTotal - $expenseTotal,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cashFlow(User $user, Carbon $from, Carbon $to, string $currency): array
    {
        $moves = [TransactionType::Transfer->value, TransactionType::Adjustment->value];
        $rows = $this->lines($user, $from, $to, $currency)
            ->join('accounting_transactions as t', 't.id', '=', 'e.transaction_id')
            ->where('l.kind', LineKind::Account->value)
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id')
            ->selectRaw('SUM(CASE WHEN t.type IN (?, ?) THEN 0 ELSE l.debit END) AS inflows', $moves)
            ->selectRaw('SUM(CASE WHEN t.type IN (?, ?) THEN 0 ELSE l.credit END) AS outflows', $moves)
            ->selectRaw('SUM(CASE WHEN t.type = ? THEN l.debit ELSE 0 END) AS transfers_in', [TransactionType::Transfer->value])
            ->selectRaw('SUM(CASE WHEN t.type = ? THEN l.credit ELSE 0 END) AS transfers_out', [TransactionType::Transfer->value])
            ->selectRaw('SUM(CASE WHEN t.type = ? THEN l.debit - l.credit ELSE 0 END) AS adjustments', [TransactionType::Adjustment->value])
            ->get()
            ->keyBy('account_id');

        $before = $this->netBefore($user, $from, $currency);
        $accounts = FinancialAccount::query()
            ->whereBelongsTo($user)
            ->where('currency', $currency)
            ->where(fn ($q) => $q->whereNull('archived_at')->orWhereIn('id', $rows->keys()))
            ->orderBy('name')
            ->get()
            ->map(function (FinancialAccount $account) use ($rows, $before) {
                $row = $rows[$account->id] ?? null;
                $opening = $account->opening_balance + (int) ($before[$account->id] ?? 0);
                $flows = [
                    'inflows' => (int) ($row->inflows ?? 0),
                    'outflows' => (int) ($row->outflows ?? 0),
                    'transfers_in' => (int) ($row->transfers_in ?? 0),
                    'transfers_out' => (int) ($row->transfers_out ?? 0),
                    'adjustments' => (int) ($row->adjustments ?? 0),
                ];

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'opening' => $opening,
                    ...$flows,
                    'closing' => $opening + $flows['inflows'] - $flows['outflows'] + $flows['transfers_in'] - $flows['transfers_out'] + $flows['adjustments'],
                ];
            })
            ->values()
            ->all();

        $inflows = array_sum(array_column($accounts, 'inflows'));
        $outflows = array_sum(array_column($accounts, 'outflows'));

        return [
            'inflows' => $inflows,
            'outflows' => $outflows,
            'net' => $inflows - $outflows,
            'adjustments' => array_sum(array_column($accounts, 'adjustments')),
            'opening' => array_sum(array_column($accounts, 'opening')),
            'closing' => array_sum(array_column($accounts, 'closing')),
            'accounts' => $accounts,
        ];
    }

    /**
     * The number of transactions behind each kind of nominal in the range, so insights only
     * speak when there is enough to go on.
     *
     * @return array{income: int, expense: int}
     */
    public function counts(User $user, Carbon $from, Carbon $to, string $currency): array
    {
        $counts = $this->lines($user, $from, $to, $currency)
            ->whereIn('l.kind', [LineKind::Income->value, LineKind::Expense->value])
            ->groupBy('l.kind')
            ->selectRaw('l.kind, COUNT(DISTINCT e.transaction_id) AS total')
            ->pluck('total', 'kind');

        return ['income' => (int) ($counts['income'] ?? 0), 'expense' => (int) ($counts['expense'] ?? 0)];
    }

    /** The most months one trend covers. */
    public const TREND_MONTHS = 24;

    /**
     * Income, spending and net income for each calendar month from `$from`'s month to `$to`'s,
     * months with nothing recorded included as zero.
     *
     * @return list<array{month: string, from: string, to: string, income: int, expenses: int, net: int}>
     */
    public function trend(User $user, Carbon $from, Carbon $to, string $currency): array
    {
        $start = $from->copy()->startOfMonth();
        $end = $to->copy()->endOfMonth()->startOfDay();
        $rows = $this->lines($user, $start, $end, $currency)
            ->whereIn('l.kind', [LineKind::Income->value, LineKind::Expense->value])
            ->groupBy('e.date', 'l.kind')
            ->selectRaw('e.date, l.kind, SUM(l.debit) AS debit, SUM(l.credit) AS credit')
            ->get();

        $months = [];
        for ($month = $start->copy(); $month->lte($end); $month->addMonthNoOverflow()) {
            $months[$month->format('Y-m')] = [
                'month' => $month->format('Y-m'),
                'from' => $month->toDateString(),
                'to' => $month->copy()->endOfMonth()->toDateString(),
                'income' => 0,
                'expenses' => 0,
                'net' => 0,
            ];
        }
        foreach ($rows as $row) {
            $key = substr((string) $row->date, 0, 7);
            if ($row->kind === LineKind::Income->value) {
                $months[$key]['income'] += (int) $row->credit - (int) $row->debit;
            } else {
                $months[$key]['expenses'] += (int) $row->debit - (int) $row->credit;
            }
        }

        return array_values(array_map(fn (array $m) => [...$m, 'net' => $m['income'] - $m['expenses']], $months));
    }

    /**
     * What was spent in each spending category over the range, net of refunds.
     *
     * @return array<int, int> Category id => amount.
     */
    public function spending(User $user, Carbon $from, Carbon $to, string $currency): array
    {
        return collect($this->byCategory($user, $from, $to, $currency, LineKind::Expense))
            ->filter(fn (array $row) => $row['id'] !== null)
            ->pluck('amount', 'id')
            ->all();
    }

    /**
     * @return list<array{id: int|null, name: string, amount: int}>
     */
    private function byCategory(User $user, Carbon $from, Carbon $to, string $currency, LineKind $kind): array
    {
        $sign = $kind === LineKind::Income ? 'SUM(l.credit) - SUM(l.debit)' : 'SUM(l.debit) - SUM(l.credit)';
        $rows = $this->lines($user, $from, $to, $currency)
            ->where('l.kind', $kind->value)
            ->groupBy('l.category_id')
            ->selectRaw("l.category_id, {$sign} AS amount")
            ->get();

        $names = Category::query()->whereBelongsTo($user)->whereIn('id', $rows->pluck('category_id')->filter())->pluck('name', 'id');

        return $rows
            ->map(fn ($row) => [
                'id' => $row->category_id === null ? null : (int) $row->category_id,
                'name' => $row->category_id === null ? 'Uncategorised' : ($names[$row->category_id] ?? 'Uncategorised'),
                'amount' => (int) $row->amount,
            ])
            ->filter(fn (array $row) => $row['amount'] !== 0)
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    private function lines(User $user, Carbon $from, Carbon $to, string $currency): Builder
    {
        return DB::table('accounting_journal_lines as l')
            ->join('accounting_journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.user_id', $user->id)
            ->where('l.currency', $currency)
            ->whereDate('e.date', '>=', $from->toDateString())
            ->whereDate('e.date', '<=', $to->toDateString())
            ->whereNull('e.reverses_id')
            ->whereNotExists(fn ($q) => $q->from('accounting_journal_entries as r')->whereColumn('r.reverses_id', 'e.id'));
    }

    /**
     * Each account's net movement before the range, counting every entry so it matches balances.
     *
     * @return Collection<int, int>
     */
    private function netBefore(User $user, Carbon $from, string $currency): Collection
    {
        return DB::table('accounting_journal_lines as l')
            ->join('accounting_journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.user_id', $user->id)
            ->where('l.currency', $currency)
            ->where('l.kind', LineKind::Account->value)
            ->whereDate('e.date', '<', $from->toDateString())
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, SUM(l.debit) - SUM(l.credit) AS net')
            ->pluck('net', 'account_id');
    }
}
