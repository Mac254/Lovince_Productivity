<?php

namespace App\Modules\Accounting\Actions;

use App\Modules\Accounting\Enums\TransactionStatus;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\AuditTrail;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edits a transaction without rewriting history: when a posted transaction's amount, account,
 * category, type or date changes, its entry is cancelled and a new one posted.
 */
final class UpdateTransaction
{
    public function __construct(private Ledger $ledger) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  Carbon|null  $base  the `updated_at` the edit started from; a newer row is a conflict
     */
    public function handle(User $user, Transaction $transaction, array $attributes, ?Carbon $base = null): Transaction
    {
        return DB::transaction(function () use ($user, $transaction, $attributes, $base) {
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_if($base !== null && $transaction->updated_at->gt($base), 409, 'This transaction was changed somewhere else. Reopen it to see the latest.');
            $before = $transaction->status;

            if ($before === TransactionStatus::Voided) {
                throw ValidationException::withMessages(['status' => 'A voided transaction can’t be edited.']);
            }

            $transaction->fill($attributes);
            $ledgerChanged = $transaction->isDirty(Transaction::LEDGER_FIELDS);

            if (($ledgerChanged || $transaction->isDirty('status')) && $transaction->isLocked()) {
                throw ValidationException::withMessages(['status' => 'A reversed transaction or a reversal can only have its description, notes and reference changed.']);
            }
            if ($ledgerChanged && $before === TransactionStatus::Reconciled) {
                throw ValidationException::withMessages(['status' => 'Set a reconciled transaction back to posted before changing its amount, account, category or date.']);
            }

            $wasPosted = $before->isPosted();
            $isPosted = $transaction->status->isPosted();
            if ($isPosted && ! $wasPosted) {
                $transaction->posted_at = now();
            }
            if (! $isPosted) {
                $transaction->posted_at = null;
            }

            $changes = AuditTrail::diff($transaction);
            $transaction->save();

            if ($wasPosted && (! $isPosted || $ledgerChanged)) {
                $this->ledger->cancel($transaction);
            }
            if ($isPosted && (! $wasPosted || $ledgerChanged)) {
                $this->ledger->post($transaction);
            }
            if ($changes !== []) {
                AuditTrail::record($user, 'transaction.updated', $transaction, $changes);
            }

            return $transaction;
        });
    }
}
