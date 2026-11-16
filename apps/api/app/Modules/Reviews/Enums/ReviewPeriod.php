<?php

namespace App\Modules\Reviews\Enums;

use Illuminate\Support\Carbon;

/** How long a review looks back: a week (Monday first), a calendar month or a calendar year. */
enum ReviewPeriod: string
{
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /** The first day of the period that contains the date. */
    public function start(Carbon $date): Carbon
    {
        $date = $date->copy()->startOfDay();

        return match ($this) {
            self::Week => $date->startOfWeek(),
            self::Month => $date->startOfMonth(),
            self::Year => $date->startOfYear(),
        };
    }

    public function end(Carbon $start): Carbon
    {
        return match ($this) {
            self::Week => $start->copy()->endOfWeek(),
            self::Month => $start->copy()->endOfMonth(),
            self::Year => $start->copy()->endOfYear(),
        };
    }

    public function next(Carbon $start): Carbon
    {
        return match ($this) {
            self::Week => $start->copy()->addWeek(),
            self::Month => $start->copy()->addMonthNoOverflow(),
            self::Year => $start->copy()->addYear(),
        };
    }

    public function label(Carbon $start): string
    {
        return match ($this) {
            self::Week => 'Week of '.$start->toFormattedDateString(),
            self::Month => $start->format('F Y'),
            self::Year => $start->format('Y'),
        };
    }

    /** The shorter reviews a longer one builds on: a month's weeks, a year's months. */
    public function shorter(): ?self
    {
        return match ($this) {
            self::Week => null,
            self::Month => self::Week,
            self::Year => self::Month,
        };
    }
}
