<?php

namespace App\Modules\Journal\Services;

use App\Modules\Blockers\Enums\BlockerStatus;
use App\Modules\Blockers\Models\Blocker;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;

/**
 * What happened on a day, for writing about it: tasks completed, work logged, blockers raised or
 * resolved, and tasks due that day still open when it ended (or now, for today).
 * Reads the Tasks and Blockers modules' records; it never changes them.
 */
class DayContext
{
    /**
     * @return array<string, mixed>
     */
    public function for(User $user, Carbon $day): array
    {
        $start = $day->copy()->startOfDay();
        $end = now()->min($start->copy()->endOfDay());
        $task = fn (?Task $t) => $t ? ['id' => $t->id, 'title' => $t->title] : null;

        $completed = Task::query()->whereBelongsTo($user)
            ->where('status', TaskStatus::Completed)
            ->whereBetween('completed_at', [$start, $end])
            ->orderBy('completed_at')
            ->get(['id', 'title']);

        $workLog = TaskNote::query()->whereBelongsTo($user)->with('task:id,title')
            ->whereDate('logged_on', $start)
            ->orderBy('id')
            ->get();

        $blockers = Blocker::query()->whereBelongsTo($user)->with('task:id,title')
            ->where(fn ($q) => $q->whereBetween('created_at', [$start, $end])
                ->orWhere(fn ($q) => $q->where('status', BlockerStatus::Resolved)->whereBetween('resolved_at', [$start, $end])))
            ->orderBy('created_at')
            ->get();

        $stillOpen = Task::query()->whereBelongsTo($user)
            ->whereDate('due_date', $start)
            ->where('status', '!=', TaskStatus::Cancelled)
            ->where(fn ($q) => $q->whereNull('completed_at')->orWhere('completed_at', '>', $end))
            ->orderBy('id')
            ->get(['id', 'title']);

        return [
            'date' => $start->toDateString(),
            'completed' => $completed->map($task)->all(),
            'work_log' => $workLog->map(fn (TaskNote $note) => [
                'id' => $note->id,
                'body' => $note->body,
                'minutes' => $note->minutes,
                'task' => $task($note->task),
            ])->all(),
            'blockers' => $blockers->map(fn (Blocker $blocker) => [
                'id' => $blocker->id,
                'description' => $blocker->description,
                'resolved' => $blocker->status === BlockerStatus::Resolved && $blocker->resolved_at <= $end,
                'task' => $task($blocker->task),
            ])->all(),
            'still_open' => $stillOpen->map($task)->all(),
        ];
    }
}
