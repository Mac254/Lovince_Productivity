<?php

namespace App\Modules\Tasks\Observers;

use App\Modules\Tasks\Actions\ScheduleNextOccurrence;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Events\WorkLogChanged;
use App\Modules\Tasks\Models\Task;

/**
 * A task's work log goes with it. The entries are removed here, not left to the database
 * cascade, so saved reports for the days they were logged on stop listing them.
 */
class TaskObserver
{
    public function __construct(private readonly ScheduleNextOccurrence $scheduleNext) {}

    public function saved(Task $task): void
    {
        if ($task->status === TaskStatus::Completed && ($task->wasRecentlyCreated || $task->wasChanged('status'))) {
            ($this->scheduleNext)($task);
        }
    }

    public function deleting(Task $task): void
    {
        $days = $task->notes()->pluck('logged_on')->unique(fn ($day) => $day->toDateString());

        $task->notes()->delete();

        if ($days->isNotEmpty()) {
            WorkLogChanged::dispatch($task->user, $days->values()->all());
        }
    }
}
