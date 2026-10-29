<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\DailyReportGenerator;

/**
 * A task's work log goes with it. The entries are removed here, not left to the database
 * cascade, so saved reports for the days they were logged on stop listing them.
 */
class TaskObserver
{
    public function __construct(private DailyReportGenerator $reports) {}

    public function deleting(Task $task): void
    {
        $days = $task->notes()->pluck('logged_on')->unique(fn ($day) => $day->toDateString());

        $task->notes()->delete();

        foreach ($days as $day) {
            $this->reports->refreshWorkLog($task->user, $day);
        }
    }
}
