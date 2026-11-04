<?php

namespace App\Modules\Accounting\Enums;

/**
 * What a journal line is booked against: one of the user's accounts, an income or expense
 * nominal (a category, or the type itself when uncategorised), or equity for adjustments.
 */
enum LineKind: string
{
    case Account = 'account';
    case Income = 'income';
    case Expense = 'expense';
    case Equity = 'equity';
}
