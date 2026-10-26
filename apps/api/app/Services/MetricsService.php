<?php

namespace App\Services;

use App\Enums\BlockerStatus;
use App\Enums\GoalStatus;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Blocker;
use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
use App\Support\TypeBreakdown;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Activity metrics for a trailing window ending today. These describe what happened;
 * they make no claim about the quality of the work.
 */
class MetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function for(User $user, int $days): array
    {
        $end = today()->endOfDay();
        $start = today()->subDays($days - 1)->startOfDay();
        $previousStart = $start->copy()->subDays($days);

        $tasks = Task::query()->whereBelongsTo($user)->get();

        $completedInRange = $tasks->filter(fn (Task $t) => $t->status === TaskStatus::Completed && $t->completed_at?->between($start, $end));
        $createdInRange = $tasks->filter(fn (Task $t) => $t->created_at->between($start, $end));
        $completedPrevious = $tasks->filter(fn (Task $t) => $t->status === TaskStatus::Completed
            && $t->completed_at?->between($previousStart, $start->copy()->subSecond()))->count();

        $withDue = $completedInRange->filter(fn (Task $t) => $t->due_date !== null);
        $onTime = $withDue->filter(fn (Task $t) => $t->completed_at->toDateString() <= $t->due_date->toDateString());

        $cycleDays = $completedInRange
            ->map(fn (Task $t) => $t->created_at->diffInHours($t->completed_at) / 24)
            ->filter(fn (float $d) => $d >= 0)
            ->sort()
            ->values();

        $blockers = Blocker::query()->whereBelongsTo($user)->get();
        $resolved = $blockers->filter(fn (Blocker $b) => $b->status === BlockerStatus::Resolved && $b->resolved_at?->between($start, $end));

        return [
            'range' => ['days' => $days, 'start' => $start->toDateString(), 'end' => $end->toDateString()],
            'totals' => [
                'completed' => $completedInRange->count(),
                'completed_previous' => $completedPrevious,
                'created' => $createdInRange->count(),
                'per_day' => round($completedInRange->count() / $days, 1),
                'on_time_rate' => $withDue->isNotEmpty() ? (int) round($onTime->count() / $withDue->count() * 100) : null,
                'on_time_sample' => $withDue->count(),
                'median_cycle_days' => $this->median($cycleDays),
                'open_now' => $tasks->filter(fn (Task $t) => in_array($t->status, TaskStatus::open(), true))->count(),
                'overdue_now' => $tasks->filter(fn (Task $t) => in_array($t->status, TaskStatus::open(), true)
                    && $t->due_date?->isBefore(today()))->count(),
                'open_blockers' => $blockers->where('status', BlockerStatus::Open)->count(),
                'blockers_resolved' => $resolved->count(),
            ],
            'daily' => $this->daily($start, $days, $createdInRange, $completedInRange),
            'heatmap' => $this->heatmap($tasks),
            'by_priority' => collect(TaskPriority::cases())->map(fn (TaskPriority $p) => [
                'key' => $p->value,
                'completed' => $completedInRange->where('priority', $p)->count(),
                'open' => $tasks->filter(fn (Task $t) => $t->priority === $p && in_array($t->status, TaskStatus::open(), true))->count(),
            ])->all(),
            'by_category' => collect(TaskCategory::cases())->map(fn (TaskCategory $c) => [
                'key' => $c->value,
                'completed' => $completedInRange->where('category', $c)->count(),
                'open' => $tasks->filter(fn (Task $t) => $t->category === $c && in_array($t->status, TaskStatus::open(), true))->count(),
            ])->all(),
            'by_type' => TypeBreakdown::rows(
                $user,
                $completedInRange,
                $tasks->filter(fn (Task $t) => in_array($t->status, TaskStatus::open(), true)),
            ),
            'goals' => Goal::query()->whereBelongsTo($user)->where('status', GoalStatus::Active)->withProgress()->orderBy('target_date')->get()
                ->map(fn (Goal $g) => [
                    'id' => $g->id,
                    'title' => $g->title,
                    'timeframe' => $g->timeframe->value,
                    'progress' => $g->progress(),
                    'completed' => (int) $g->completed_tasks_count,
                    'total' => (int) $g->eligible_tasks_count,
                ])->all(),
        ];
    }

    /**
     * @param  Collection<int, Task>  $created
     * @param  Collection<int, Task>  $completed
     * @return list<array{date: string, created: int, completed: int}>
     */
    private function daily(Carbon $start, int $days, Collection $created, Collection $completed): array
    {
        $createdByDay = $created->countBy(fn (Task $t) => $t->created_at->toDateString());
        $completedByDay = $completed->countBy(fn (Task $t) => $t->completed_at->toDateString());

        return collect(range(0, $days - 1))->map(function (int $offset) use ($start, $createdByDay, $completedByDay) {
            $date = $start->copy()->addDays($offset)->toDateString();

            return ['date' => $date, 'created' => $createdByDay[$date] ?? 0, 'completed' => $completedByDay[$date] ?? 0];
        })->all();
    }

    /**
     * Completions per day for the last 26 full weeks plus this week, Monday first, independent of the range.
     *
     * @param  Collection<int, Task>  $tasks
     * @return list<array{date: string, count: int}>
     */
    private function heatmap(Collection $tasks): array
    {
        $first = today()->startOfWeek()->subWeeks(26);
        $counts = $tasks
            ->filter(fn (Task $t) => $t->status === TaskStatus::Completed && $t->completed_at?->greaterThanOrEqualTo($first))
            ->countBy(fn (Task $t) => $t->completed_at->toDateString());

        $days = (int) $first->diffInDays(today()) + 1;

        return collect(range(0, $days - 1))->map(function (int $offset) use ($first, $counts) {
            $date = $first->copy()->addDays($offset)->toDateString();

            return ['date' => $date, 'count' => $counts[$date] ?? 0];
        })->all();
    }

    /**
     * @param  Collection<int, float>  $values  sorted ascending
     */
    private function median(Collection $values): ?float
    {
        $count = $values->count();
        if ($count === 0) {
            return null;
        }
        $mid = intdiv($count, 2);
        $median = $count % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;

        return round($median, 1);
    }
}
