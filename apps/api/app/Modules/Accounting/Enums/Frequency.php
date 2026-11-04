<?php

namespace App\Modules\Accounting\Enums;

use Carbon\CarbonInterface;

enum Frequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * The date of the payment `$n` periods after the first. Counting from the first keeps a rule
     * that starts on the 31st on the last day of shorter months without drifting to the 28th.
     */
    public function nth(CarbonInterface $first, int $n): CarbonInterface
    {
        return match ($this) {
            self::Weekly => $first->copy()->addWeeks($n),
            self::Monthly => $first->copy()->addMonthsNoOverflow($n),
            self::Yearly => $first->copy()->addYearsNoOverflow($n),
        };
    }
}
