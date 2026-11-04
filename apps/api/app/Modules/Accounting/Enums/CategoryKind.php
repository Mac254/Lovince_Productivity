<?php

namespace App\Modules\Accounting\Enums;

enum CategoryKind: string
{
    case Income = 'income';
    case Expense = 'expense';
}
