<?php

namespace App\Modules\Accounting\Actions;

use App\Modules\Accounting\Enums\TransactionStatus;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\AuditTrail;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks a mistaken transaction as never having happened. It stays on record; its entry is
 * cancelled on the original date. Voiding twice changes nothing.
 */
final class VoidTransaction
{
    public function __construct(private Ledger $ledger) {}

    public function handle(User $user, Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($user, $transaction) {
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($transaction->status === TransactionStatus::Voided) {
                return $transaction;
            }
            if ($transaction->isLocked()) {
                throw ValidationException::withMessages(['status' => 'A reversed transaction or a reversal can’t be voided.']);
            }
            if ($transaction->status === TransactionStatus::Reconciled) {
                throw ValidationException::withMessages(['status' => 'A reconciled transaction can’t be voided. Reverse it instead.']);
            }

            if ($transaction->status->isPosted()) {
                $this->ledger->cancel($transaction);
            }
            $from = $transaction->status->value;
            $transaction->forceFill(['status' => TransactionStatus::Voided, 'voided_at' => now()])->save();
            AuditTrail::record($user, 'transaction.voided', $transaction, ['status' => [$from, TransactionStatus::Voided->value]]);

            return $transaction;
        });
    }
}
