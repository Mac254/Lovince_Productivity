<?php

namespace App\Modules\Accounting\Enums;

enum GoalKind: string
{
    case Savings = 'savings';
    case EmergencyFund = 'emergency_fund';
    case Investment = 'investment';
    case Other = 'other';
}
