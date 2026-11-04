<?php

namespace App\Modules\Accounting\Enums;

enum AccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MobileMoney = 'mobile_money';
    case Savings = 'savings';
    case Sacco = 'sacco';
    case MoneyMarket = 'money_market';
    case Investment = 'investment';
    case Credit = 'credit';
    case Other = 'other';
}
