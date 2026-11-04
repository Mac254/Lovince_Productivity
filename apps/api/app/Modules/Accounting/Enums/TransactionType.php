<?php

namespace App\Modules\Accounting\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case Interest = 'interest';
    case Fee = 'fee';

    /**
     * Which way money moves through the transaction's account. A transfer leaves its account;
     * an adjustment says which way it goes.
     */
    public function direction(): ?TransactionDirection
    {
        return match ($this) {
            self::Income, self::Interest, self::Refund => TransactionDirection::In,
            self::Expense, self::Fee, self::Transfer => TransactionDirection::Out,
            self::Adjustment => null,
        };
    }

    /**
     * The kind of category the type is booked against, or null when it takes none.
     */
    public function categoryKind(): ?CategoryKind
    {
        return match ($this) {
            self::Income, self::Interest => CategoryKind::Income,
            self::Expense, self::Fee, self::Refund => CategoryKind::Expense,
            self::Transfer, self::Adjustment => null,
        };
    }
}
