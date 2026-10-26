<?php

namespace App\Services;

use App\Enums\BlockerStatus;
use App\Enums\GoalStatus;
use App\Enums\TaskCategory;
use App\Enums\TaskStatus;
use App\Models\Blocker;
use App\Models\DailyReport;
use App\Models\Goal;
use App\Models\JournalEntry;
use App\Models\Task;
use App\Models\User;
use App\Support\TypeBreakdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Snapshots one day for one user. Counts come from timestamps, so they are exact for any date;
 * "still open" lists reflect the moment of generation, which is why the scheduler runs at day end.
 */
class DailyReportGenerator
{
    private const LIST_LIMIT = 20;

    public function generate(User $user, Carbon $date): DailyReport
    {
        $date = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $tasks = fn (): Builder => Task::query()->whereBelongsTo($user);

        $completed = $tasks()->with(['goal:id,title', 'type:id,name,tone'])
            ->whereBetween('completed_at', [$date, $dayEnd])
            ->where('status', TaskStatus::Completed)
            ->orderBy('completed_at')
            ->get();

        $due = $tasks()->with(['goal:id,title', 'type:id,name,tone'])->whereDate('due_date', $date)->where('status', '!=', TaskStatus::Cancelled)->get();
        $dueCompleted = $due->where('status', TaskStatus::Completed)->count();
        $carriedOver = $due->filter(fn (Task $task) => in_array($task->status, TaskStatus::open(), true));

        $overdue = $tasks()->with(['goal:id,title', 'type:id,name,tone'])->open()->whereDate('due_date', '<', $date)->orderBy('due_date')->get();

        // Today's plan, as the Today page shows it: due this day or starred.
        $inPlan = fn (Task $task) => $task->is_highlighted || $task->due_date?->isSameDay($date);
        $plannedCompleted = $completed->filter($inPlan)->count();
        $plannedOpen = $tasks()->open()->where(fn (Builder $q) => $q->whereDate('due_date', $date)->orWhere('is_highlighted', true))->count();

        $onTimeEligible = $completed->filter(fn (Task $task) => $task->due_date !== null);
        $onTime = $onTimeEligible->filter(fn (Task $task) => $task->completed_at->toDateString() <= $task->due_date->toDateString());

        $openBlockers = Blocker::query()->whereBelongsTo($user)->open()->with('task:id,title')->latest()->get();
        $blockersRaised = Blocker::query()->whereBelongsTo($user)->whereBetween('created_at', [$date, $dayEnd])->count();
        $blockersResolved = Blocker::query()->whereBelongsTo($user)->with('task:id,title')
            ->where('status', BlockerStatus::Resolved)
            ->whereBetween('resolved_at', [$date, $dayEnd])
            ->get();

        $summary = [
            'completed' => $completed->count(),
            'created' => $tasks()->whereBetween('created_at', [$date, $dayEnd])->count(),
            'due' => $due->count(),
            'due_completed' => $dueCompleted,
            'completion_rate' => $due->count() > 0 ? (int) round($dueCompleted / $due->count() * 100) : null,
            'on_time_rate' => $onTimeEligible->count() > 0 ? (int) round($onTime->count() / $onTimeEligible->count() * 100) : null,
            'overdue' => $overdue->count(),
            'open_blockers' => $openBlockers->count(),
            'blockers_raised' => $blockersRaised,
            'blockers_resolved' => $blockersResolved->count(),
            'planned' => $plannedCompleted + $plannedOpen,
            'planned_completed' => $plannedCompleted,
            'high_priority_completed' => $completed->filter(fn (Task $task) => in_array($task->priority->value, ['high', 'urgent'], true))->count(),
        ];

        $data = [
            'summary' => $summary,
            'narrative' => $this->narrative($summary),
            'completed_tasks' => $this->taskList($completed),
            'by_type' => TypeBreakdown::rows($user, $completed, collect()),
            'carried_over' => $this->taskList($carriedOver),
            'overdue_tasks' => $this->taskList($overdue->take(self::LIST_LIMIT)),
            'blockers_open' => $this->blockerList($openBlockers->take(self::LIST_LIMIT)),
            'blockers_resolved' => $this->blockerList($blockersResolved),
            'goals' => Goal::query()->whereBelongsTo($user)->where('status', GoalStatus::Active)->withProgress()->orderBy('target_date')->get()
                ->map(fn (Goal $goal) => [
                    'id' => $goal->id,
                    'title' => $goal->title,
                    'timeframe' => $goal->timeframe->value,
                    'progress' => $goal->progress(),
                    'completed' => (int) $goal->completed_tasks_count,
                    'total' => (int) $goal->eligible_tasks_count,
                ])->all(),
            'integration' => [
                'development_completed' => $tasks()->where('category', TaskCategory::Development)->where('status', TaskStatus::Completed)->count(),
                'integration_pending' => $tasks()->where('category', TaskCategory::Integration)->open()->count(),
            ],
            'journal' => JournalEntry::query()->whereBelongsTo($user)->whereDate('date', $date)->value('body'),
        ];

        // Looked up by calendar date: the date cast stores a time part, so a plain where() would miss.
        $report = DailyReport::query()->whereBelongsTo($user)->whereDate('date', $date)->first()
            ?? $user->dailyReports()->make(['date' => $date->toDateString()]);

        $report->fill([
            'completed_count' => $summary['completed'],
            'created_count' => $summary['created'],
            'due_count' => $summary['due'],
            'due_completed_count' => $summary['due_completed'],
            'overdue_count' => $summary['overdue'],
            'open_blockers_count' => $summary['open_blockers'],
            'data' => $data,
            'generated_at' => now(),
        ])->save();

        return $report;
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return list<array<string, mixed>>
     */
    private function taskList(Collection $tasks): array
    {
        return $tasks->map(fn (Task $task) => [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'category' => $task->category->value,
            'type' => $task->type ? ['id' => $task->type->id, 'name' => $task->type->name, 'tone' => $task->type->tone->value] : null,
            'due_date' => $task->due_date?->toDateString(),
            'goal' => $task->goal ? ['id' => $task->goal->id, 'title' => $task->goal->title] : null,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Blocker>  $blockers
     * @return list<array<string, mixed>>
     */
    private function blockerList(Collection $blockers): array
    {
        return $blockers->map(fn (Blocker $blocker) => [
            'id' => $blocker->id,
            'description' => $blocker->description,
            'task' => $blocker->task ? ['id' => $blocker->task->id, 'title' => $blocker->task->title] : null,
            'created_at' => $blocker->created_at?->toIso8601String(),
        ])->values()->all();
    }

    /**
     * A plain-language summary built only from the numbers — no judgement of quality.
     *
     * @param  array<string, int|null>  $s
     */
    private function narrative(array $s): string
    {
        $parts = [];
        $parts[] = match ($s['completed']) {
            0 => 'No tasks were completed',
            1 => '1 task was completed',
            default => "{$s['completed']} tasks were completed",
        }.($s['high_priority_completed'] > 0 ? " ({$s['high_priority_completed']} high priority)" : '').'.';

        if (($s['planned'] ?? 0) > 0) {
            $parts[] = "{$s['planned_completed']} of {$s['planned']} planned ".($s['planned'] === 1 ? 'task was' : 'tasks were').' done.';
        }
        if ($s['due'] > 0) {
            $parts[] = "{$s['due_completed']} of {$s['due']} tasks due today were done.";
        }
        if ($s['overdue'] > 0) {
            $parts[] = "{$s['overdue']} ".($s['overdue'] === 1 ? 'task is' : 'tasks are').' overdue.';
        }
        if ($s['open_blockers'] > 0) {
            $parts[] = "{$s['open_blockers']} ".($s['open_blockers'] === 1 ? 'blocker remains' : 'blockers remain').' open.';
        }
        if ($s['blockers_resolved'] > 0) {
            $parts[] = "{$s['blockers_resolved']} ".($s['blockers_resolved'] === 1 ? 'blocker was' : 'blockers were').' resolved.';
        }

        return implode(' ', $parts);
    }
}
