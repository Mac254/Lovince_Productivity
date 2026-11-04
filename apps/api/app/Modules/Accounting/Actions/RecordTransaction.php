<?php

namespace App\Modules\Accounting\Actions;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\AuditTrail;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Users\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Records a transaction once per idempotency key: a repeated submission returns the first
 * transaction and posts nothing.
 */
final class RecordTransaction
{
    public function __construct(private Ledger $ledger) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: Transaction, 1: bool} The transaction and whether it was created now.
     */
    public function handle(User $user, array $attributes, string $key): array
    {
        $existing = $this->find($user, $key);
        if ($existing !== null) {
            return [$existing, false];
        }

        try {
            $transaction = DB::transaction(function () use ($user, $attributes, $key) {
                $transaction = new Transaction($attributes + ['idempotency_key' => $key]);
                $transaction->user()->associate($user);
                $transaction->posted_at = $transaction->status->isPosted() ? now() : null;
                $transaction->save();

                if ($transaction->status->isPosted()) {
                    $this->ledger->post($transaction);
                }
                AuditTrail::record($user, 'transaction.created', $transaction, $this->snapshot($transaction));

                return $transaction;
            });
        } catch (UniqueConstraintViolationException) {
            return [$this->find($user, $key), false];
        }

        return [$transaction, true];
    }

    private function find(User $user, string $key): ?Transaction
    {
        return Transaction::query()->whereBelongsTo($user)->where('idempotency_key', $key)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Transaction $transaction): array
    {
        return [
            'type' => $transaction->type->value,
            'status' => $transaction->status->value,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'account_id' => $transaction->account_id,
            'date' => $transaction->date->toDateString(),
        ];
    }
}
