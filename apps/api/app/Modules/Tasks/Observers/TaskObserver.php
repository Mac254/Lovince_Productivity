<?php

namespace App\Modules\Tasks\Observers;

use App\Modules\Tasks\Actions\ScheduleNextOccurrence;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Events\CompletionsChanged;
use App\Modules\Tasks\Events\WorkLogChanged;
use App\Modules\Tasks\Models\Task;

/**
 * A task's work log goes with it. The entries are removed here, not left to the database
 * cascade, so saved reports for the days they were logged on stop listing them. Deleting a task
 * finished on an earlier day refreshes that day's saved report too, and reopening a repeating task
 * withdraws the next copy it scheduled unless that copy has been worked on.
 */
class TaskObserver
{
    public function __construct(private readonly ScheduleNextOccurrence $scheduleNext) {}

    public function saved(Task $task): void
    {
        if ($task->wasChanged('status') && $task->getOriginal('status') === TaskStatus::Completed && $task->next_occurrence_id !== null) {
            $this->withdrawNext($task);
        }

        if ($task->status === TaskStatus::Completed && ($task->wasRecentlyCreated || $task->wasChanged('status'))) {
            ($this->scheduleNext)($task);
        }

        if (! $task->wasRecentlyCreated && $task->wasChanged('completed_at')) {
            $days = collect([$task->getOriginal('completed_at'), $task->completed_at])
                ->filter(fn ($at) => $at !== null && $at->lt(today()))
                ->map(fn ($at) => $at->copy()->startOfDay())
                ->unique(fn ($day) => $day->toDateString());

            if ($days->isNotEmpty()) {
                CompletionsChanged::dispatch($task->user, $days->values()->all());
            }
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

    private function withdrawNext(Task $task): void
    {
        $next = Task::query()->withCount('notes')->find($task->next_occurrence_id);
        $untouched = $next !== null
            && $next->status === TaskStatus::NotStarted
            && $next->notes_count === 0
            && $next->next_occurrence_id === null
            && $next->updated_at->equalTo($next->created_at)
            && ! $next->checklistItems()->where('done', true)->exists();

        if (! $untouched && $next !== null) {
            return;
        }

        $task->forceFill(['next_occurrence_id' => null])->saveQuietly();
        $next?->delete();
    }

    public function deleted(Task $task): void
    {
        if ($task->completed_at?->lt(today())) {
            CompletionsChanged::dispatch($task->user, [$task->completed_at->copy()->startOfDay()]);
        }
    }
}
