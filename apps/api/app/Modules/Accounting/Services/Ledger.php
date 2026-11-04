<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\CategoryKind;
use App\Modules\Accounting\Enums\LineKind;
use App\Modules\Accounting\Enums\TransactionDirection;
use App\Modules\Accounting\Enums\TransactionStatus;
use App\Modules\Accounting\Enums\TransactionType;
use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The double-entry ledger behind transactions. Every posted transaction has one effective
 * journal entry whose debits equal its credits; balances are summed from the lines.
 *
 * An account line debits money in and credits money out. Income is credited to an income
 * nominal, spending debited to an expense nominal, and a transfer moves between two accounts.
 */
final class Ledger
{
    public function post(Transaction $transaction): JournalEntry
    {
        $entry = JournalEntry::query()->create([
            'user_id' => $transaction->user_id,
            'transaction_id' => $transaction->id,
            'date' => $transaction->date->toDateString(),
        ]);
        $entry->lines()->createMany($this->lines($transaction));
        $this->ensureBalanced($entry);

        return $entry;
    }

    /**
     * Cancels the transaction's effective entry with a mirror image on the same date, so the
     * period it was booked in nets to zero and the original stays in the history.
     */
    public function cancel(Transaction $transaction): void
    {
        $entry = JournalEntry::query()->where('transaction_id', $transaction->id)->effective()->with('lines')->first();
        if ($entry === null) {
            return;
        }

        $mirror = JournalEntry::query()->create([
            'user_id' => $entry->user_id,
            'transaction_id' => $entry->transaction_id,
            'reverses_id' => $entry->id,
            'date' => $entry->date->toDateString(),
        ]);
        $mirror->lines()->createMany($entry->lines->map(fn ($line) => [
            'kind' => $line->kind,
            'account_id' => $line->account_id,
            'category_id' => $line->category_id,
            'debit' => $line->credit,
            'credit' => $line->debit,
            'currency' => $line->currency,
        ])->all());
        $this->ensureBalanced($mirror);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lines(Transaction $transaction): array
    {
        $amount = $transaction->amount;
        $in = $transaction->direction === TransactionDirection::In;
        $line = fn (LineKind $kind, ?int $account, ?int $category, bool $debit) => [
            'kind' => $kind,
            'account_id' => $account,
            'category_id' => $category,
            'debit' => $debit ? $amount : 0,
            'credit' => $debit ? 0 : $amount,
            'currency' => $transaction->currency,
        ];

        $counter = $transaction->type === TransactionType::Transfer
            ? $line(LineKind::Account, $transaction->to_account_id, null, $in === false)
            : $line($this->nominal($transaction), null, $transaction->category_id, $in === false);

        return [$line(LineKind::Account, $transaction->account_id, null, $in), $counter];
    }

    /**
     * Sets `balance`, `available_balance`, `total_inflows` and `total_outflows` on each account.
     * Balance counts only posted entries; available balance also holds back pending outflows.
     * Inflows and outflows count entries still in force, so a correction is not counted twice.
     *
     * @param  Collection<int, FinancialAccount>  $accounts
     * @return Collection<int, FinancialAccount>
     */
    public function balances(Collection $accounts): Collection
    {
        $ids = $accounts->pluck('id')->all();
        if ($ids === []) {
            return $accounts;
        }

        $net = DB::table('accounting_journal_lines')
            ->whereIn('account_id', $ids)
            ->groupBy('account_id')
            ->selectRaw('account_id, SUM(debit) - SUM(credit) AS net')
            ->pluck('net', 'account_id');

        $gross = DB::table('accounting_journal_lines as l')
            ->join('accounting_journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->whereIn('l.account_id', $ids)
            ->whereNull('e.reverses_id')
            ->whereNotExists(fn ($q) => $q->from('accounting_journal_entries as r')->whereColumn('r.reverses_id', 'e.id'))
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, SUM(l.debit) AS inflows, SUM(l.credit) AS outflows')
            ->get()
            ->keyBy('account_id');

        $pending = Transaction::query()
            ->whereIn('account_id', $ids)
            ->where('status', TransactionStatus::Pending)
            ->where('direction', TransactionDirection::Out)
            ->groupBy('account_id')
            ->selectRaw('account_id, SUM(amount) AS total')
            ->pluck('total', 'account_id');

        return $accounts->each(function (FinancialAccount $account) use ($net, $gross, $pending) {
            $account->balance = $account->opening_balance + (int) ($net[$account->id] ?? 0);
            $account->available_balance = $account->balance - (int) ($pending[$account->id] ?? 0);
            $account->total_inflows = (int) ($gross[$account->id]->inflows ?? 0);
            $account->total_outflows = (int) ($gross[$account->id]->outflows ?? 0);
        });
    }

    private function nominal(Transaction $transaction): LineKind
    {
        if ($transaction->type === TransactionType::Adjustment) {
            return LineKind::Equity;
        }

        return $transaction->type->categoryKind() === CategoryKind::Income ? LineKind::Income : LineKind::Expense;
    }

    private function ensureBalanced(JournalEntry $entry): void
    {
        $totals = $entry->lines()->selectRaw('SUM(debit) AS debits, SUM(credit) AS credits')->first();
        if ((int) $totals->debits !== (int) $totals->credits || (int) $totals->debits === 0) {
            throw new LogicException("Journal entry {$entry->id} does not balance.");
        }
    }
}
