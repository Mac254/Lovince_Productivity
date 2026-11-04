<?php

namespace App\Modules\Accounting\Actions;

use App\Modules\Accounting\Enums\TransactionStatus;
use App\Modules\Accounting\Enums\TransactionType;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\AuditTrail;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Users\Models\User;
use App\Support\Contract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Undoes a posted transaction with an opposite one on a date of the user's choosing, so both
 * stay in the history and earlier periods are untouched. A transaction is reversed at most once.
 */
final class ReverseTransaction
{
    public function __construct(private Ledger $ledger) {}

    /**
     * @return array{0: Transaction, 1: bool} The reversal and whether it was created now.
     */
    public function handle(User $user, Transaction $transaction, ?Carbon $date = null): array
    {
        return DB::transaction(function () use ($user, $transaction, $date) {
            $original = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);

            $existing = $original->reversal()->first();
            if ($existing !== null) {
                return [$existing, false];
            }
            if ($original->reversal_of_id !== null) {
                throw ValidationException::withMessages(['transaction' => 'A reversal can’t itself be reversed.']);
            }
            if (! $original->status->isPosted()) {
                throw ValidationException::withMessages(['transaction' => 'Only posted transactions can be reversed. Void a pending one instead.']);
            }

            $transfer = $original->type === TransactionType::Transfer;
            $reversal = new Transaction([
                'type' => $original->type,
                'direction' => $transfer ? $original->direction : $original->direction->opposite(),
                'account_id' => $transfer ? $original->to_account_id : $original->account_id,
                'to_account_id' => $transfer ? $original->account_id : null,
                'category_id' => $original->category_id,
                'amount' => $original->amount,
                'currency' => $original->currency,
                'date' => ($date ?? today())->toDateString(),
                'description' => Str::limit('Reversal: '.$original->description, Contract::ACCOUNTING_DESCRIPTION_MAX - 1, '…'),
                'reference' => $original->reference,
                'status' => TransactionStatus::Posted,
                'reversal_of_id' => $original->id,
                'posted_at' => now(),
            ]);
            $reversal->user()->associate($user);
            $reversal->save();

            $this->ledger->post($reversal);
            AuditTrail::record($user, 'transaction.reversed', $original, ['reversal_id' => $reversal->id]);
            AuditTrail::record($user, 'transaction.created', $reversal, ['reversal_of_id' => $original->id]);

            return [$reversal, true];
        });
    }
}
