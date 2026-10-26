<?php

namespace App\Enums;

enum TaskStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Statuses that still represent outstanding work.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::NotStarted, self::InProgress, self::Blocked];
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::open());
    }
}
