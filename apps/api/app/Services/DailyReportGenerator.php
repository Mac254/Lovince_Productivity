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
use App\Models\TaskNote;
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

    /**
     * Every figure describes the day as it stood at its cut-off: the end of the day, or now while
     * the day is still running. State is rebuilt from timestamps (created, completed, resolved),
     * so a report written after the day — a backfill, or the run that finalises it after
     * midnight — matches what was true then, not what is true now.
     */
    public function generate(User $user, Carbon $date): DailyReport
    {
        $date = $date->copy()->startOfDay();
        $asOf = now()->min($date->copy()->endOfDay());
        $relations = ['goal:id,title', 'type:id,name,tone'];
        $tasks = fn (): Builder => Task::query()->whereBelongsTo($user)->where('created_at', '<=', $asOf);
        $doneBy = fn (Builder $q) => $q->where('status', TaskStatus::Completed)->where('completed_at', '<=', $asOf);
        // Open at the cut-off: not cancelled, and not yet completed then (finished later still counts).
        $openThen = fn (): Builder => $tasks()->where('status', '!=', TaskStatus::Cancelled)
            ->where(fn (Builder $q) => $q->whereNull('completed_at')->orWhere('completed_at', '>', $asOf));

        $completed = $tasks()->with($relations)
            ->where('status', TaskStatus::Completed)
            ->whereBetween('completed_at', [$date, $asOf])
            ->orderBy('completed_at')
            ->get();

        $due = $tasks()->with($relations)->whereDate('due_date', $date)->where('status', '!=', TaskStatus::Cancelled)->get();
        $dueCompleted = $due->filter(fn (Task $task) => $task->status === TaskStatus::Completed && $task->completed_at <= $asOf)->count();
        $carriedOver = $openThen()->with($relations)->whereDate('due_date', $date)->get();

        $overdue = $openThen()->with($relations)->whereDate('due_date', '<', $date)->orderBy('due_date')->get();

        // The day's plan, as the Today page shows it: due this day or starred (stars are as they are now).
        $inPlan = fn (Task $task) => $task->is_highlighted || $task->due_date?->isSameDay($date);
        $plannedCompleted = $completed->filter($inPlan)->count();
        $plannedOpen = fn (): Builder => $openThen()->where(fn (Builder $q) => $q->whereDate('due_date', $date)->orWhere('is_highlighted', true));

        $workLog = $this->workLogFor($user, $date);

        $onTimeEligible = $completed->filter(fn (Task $task) => $task->due_date !== null);
        $onTime = $onTimeEligible->filter(fn (Task $task) => $task->completed_at->toDateString() <= $task->due_date->toDateString());

        $blockers = fn (): Builder => Blocker::query()->whereBelongsTo($user)->where('created_at', '<=', $asOf);
        $openBlockers = $blockers()->with('task:id,title')
            ->where(fn (Builder $q) => $q->where('status', BlockerStatus::Open)
                ->orWhere(fn (Builder $q) => $q->where('status', BlockerStatus::Resolved)->where('resolved_at', '>', $asOf)))
            ->latest()
            ->get();
        $blockersRaised = $blockers()->where('created_at', '>=', $date)->count();
        $blockersResolved = $blockers()->with('task:id,title')
            ->where('status', BlockerStatus::Resolved)
            ->whereBetween('resolved_at', [$date, $asOf])
            ->get();

        $summary = [
            'completed' => $completed->count(),
            'created' => $tasks()->where('created_at', '>=', $date)->count(),
            'due' => $due->count(),
            'due_completed' => $dueCompleted,
            'completion_rate' => $due->count() > 0 ? (int) round($dueCompleted / $due->count() * 100) : null,
            'on_time_rate' => $onTimeEligible->count() > 0 ? (int) round($onTime->count() / $onTimeEligible->count() * 100) : null,
            'overdue' => $overdue->count(),
            'open_blockers' => $openBlockers->count(),
            'blockers_raised' => $blockersRaised,
            'blockers_resolved' => $blockersResolved->count(),
            'planned' => $plannedCompleted + $plannedOpen()->count(),
            'planned_completed' => $plannedCompleted,
            'planned_worked' => $plannedOpen()->whereHas('notes', fn (Builder $q) => $q->whereDate('logged_on', $date))->count(),
            'notes_logged' => $workLog->count(),
            'minutes_logged' => (int) $workLog->sum('minutes'),
            'high_priority_completed' => $completed->filter(fn (Task $task) => in_array($task->priority->value, ['high', 'urgent'], true))->count(),
        ];

        $data = [
            'summary' => $summary,
            'narrative' => $this->narrative($summary),
            'completed_tasks' => $this->taskList($completed),
            'by_type' => TypeBreakdown::rows($user, $completed, collect()),
            'work_log' => $this->workLogList($workLog),
            'carried_over' => $this->taskList($carriedOver),
            'overdue_tasks' => $this->taskList($overdue->take(self::LIST_LIMIT)),
            'blockers_open' => $this->blockerList($openBlockers->take(self::LIST_LIMIT)),
            'blockers_resolved' => $this->blockerList($blockersResolved),
            'goals' => Goal::query()->whereBelongsTo($user)->where('status', GoalStatus::Active)->where('created_at', '<=', $asOf)
                ->withCount([
                    'tasks as eligible_tasks_count' => fn (Builder $q) => $q->where('created_at', '<=', $asOf)->where('status', '!=', TaskStatus::Cancelled),
                    'tasks as completed_tasks_count' => fn (Builder $q) => $q->where('created_at', '<=', $asOf)->where($doneBy),
                ])
                ->orderBy('target_date')
                ->get()
                ->map(fn (Goal $goal) => [
                    'id' => $goal->id,
                    'title' => $goal->title,
                    'timeframe' => $goal->timeframe->value,
                    'progress' => $goal->progress(),
                    'completed' => (int) $goal->completed_tasks_count,
                    'total' => (int) $goal->eligible_tasks_count,
                ])->all(),
            'integration' => [
                'development_completed' => $tasks()->where('category', TaskCategory::Development)->where($doneBy)->count(),
                'integration_pending' => $openThen()->where('category', TaskCategory::Integration)->count(),
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
     * Brings a saved report's work log up to date after work logged for its day changed.
     * The rest of the report stays as it was recorded.
     */
    public function refreshWorkLog(User $user, Carbon $date): void
    {
        $report = DailyReport::query()->whereBelongsTo($user)->whereDate('date', $date)->first();
        if (! $report) {
            return;
        }

        $workLog = $this->workLogFor($user, $date->copy()->startOfDay());
        $data = $report->data;
        $data['summary']['notes_logged'] = $workLog->count();
        $data['summary']['minutes_logged'] = (int) $workLog->sum('minutes');
        $data['narrative'] = $this->narrative($data['summary']);
        $data['work_log'] = $this->workLogList($workLog);

        $report->update(['data' => $data]);
    }

    /**
     * @return Collection<int, TaskNote>
     */
    private function workLogFor(User $user, Carbon $date): Collection
    {
        return TaskNote::query()->whereBelongsTo($user)->with('task:id,title')
            ->whereDate('logged_on', $date)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, TaskNote>  $workLog
     * @return list<array<string, mixed>>
     */
    private function workLogList(Collection $workLog): array
    {
        return $workLog->map(fn (TaskNote $note) => [
            'id' => $note->id,
            'body' => $note->body,
            'minutes' => $note->minutes,
            'task' => $note->task ? ['id' => $note->task->id, 'title' => $note->task->title] : null,
        ])->values()->all();
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

    private function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return trim(($hours ? "{$hours}h " : '').($rest ? "{$rest}m" : ''));
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
        if (($s['notes_logged'] ?? 0) > 0) {
            $parts[] = "{$s['notes_logged']} work ".($s['notes_logged'] === 1 ? 'note was' : 'notes were').' logged'
                .($s['minutes_logged'] > 0 ? ' ('.$this->duration($s['minutes_logged']).')' : '').'.';
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
