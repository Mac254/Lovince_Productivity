<?php

namespace App\Modules\Accounting\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Reconciled = 'reconciled';
    case Voided = 'voided';

    /**
     * Whether the transaction is in the ledger and so moves balances.
     */
    public function isPosted(): bool
    {
        return $this === self::Posted || $this === self::Reconciled;
    }
}
