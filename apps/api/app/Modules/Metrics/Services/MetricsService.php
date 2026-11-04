<?php

namespace App\Modules\Metrics\Services;

use App\Modules\Achievements\Models\Achievement;
use App\Modules\Blockers\Enums\BlockerStatus;
use App\Modules\Blockers\Models\Blocker;
use App\Modules\Clock\Models\FocusSession;
use App\Modules\Clock\Services\FocusStats;
use App\Modules\Goals\Enums\GoalStatus;
use App\Modules\Goals\Models\Goal;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Tasks\Enums\TaskCategory;
use App\Modules\Tasks\Enums\TaskPriority;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Tasks\Services\TypeBreakdown;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Activity metrics for a trailing window ending today. These describe what happened;
 * they make no claim about the quality of the work.
 */
class MetricsService
{
    public function __construct(
        private readonly FocusStats $focus,
        private readonly Insights $insights,
        private readonly ActivityDays $activity,
    ) {}

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

        // Work logged, by the day it was done (`logged_on`).
        $notes = TaskNote::query()->whereBelongsTo($user)
            ->whereDate('logged_on', '>=', $previousStart)
            ->whereDate('logged_on', '<=', $end)
            ->with('task:id,task_type_id,category')
            ->get(['id', 'task_id', 'logged_on', 'minutes']);
        $notesInRange = $notes->filter(fn (TaskNote $n) => $n->logged_on->greaterThanOrEqualTo($start->copy()->startOfDay()));
        $minutesPrevious = (int) $notes->filter(fn (TaskNote $n) => $n->logged_on->lessThan($start->copy()->startOfDay()))->sum('minutes');

        $blockers = Blocker::query()->whereBelongsTo($user)->get();
        $resolved = $blockers->filter(fn (Blocker $b) => $b->status === BlockerStatus::Resolved && $b->resolved_at?->between($start, $end));

        // Only tasks with time logged say how long the work took; one with no log would read as instant.
        $withEstimate = $completedInRange->filter(fn (Task $t) => $t->estimate_minutes !== null);
        $loggedByTask = $withEstimate->isEmpty() ? collect() : TaskNote::query()
            ->whereIn('task_id', $withEstimate->modelKeys())
            ->groupBy('task_id')
            ->selectRaw('task_id, sum(minutes) as minutes')
            ->pluck('minutes', 'task_id');
        $estimated = $withEstimate->filter(fn (Task $t) => ($loggedByTask[$t->id] ?? 0) > 0);
        $estimatedLogged = (int) $estimated->sum(fn (Task $t) => $loggedByTask[$t->id]);
        $estimatedTotal = (int) $estimated->sum('estimate_minutes');

        $focus = $this->focus->between($user, $previousStart, $end);
        $focusInRange = $focus->filter(fn (FocusSession $f) => $f->started_on->greaterThanOrEqualTo($start->copy()->startOfDay()));
        $focusByDay = FocusStats::minutesByDay($focusInRange);

        $heatmap = $this->activity->between($user, today()->startOfWeek()->subWeeks(26), today());

        $metrics = [
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
                'entries_logged' => $notesInRange->count(),
                'minutes_logged' => (int) $notesInRange->sum('minutes'),
                'minutes_logged_previous' => $minutesPrevious,
                'estimated_tasks' => $estimated->count(),
                'estimated_minutes' => $estimatedTotal,
                'estimated_logged_minutes' => $estimatedLogged,
                'estimate_accuracy' => $estimatedTotal > 0 ? (int) round($estimatedLogged / $estimatedTotal * 100) : null,
            ],
            'daily' => array_map(
                fn (array $day) => $day + ['focus' => $focusByDay[$day['date']] ?? 0],
                $this->daily($start, $days, $createdInRange, $completedInRange, $notesInRange),
            ),
            'focus' => $this->focusSummary($user, $focusInRange, $focus->diff($focusInRange), $days),
            'heatmap' => $heatmap,
            'consistency' => ActivityDays::streaks($heatmap, today()->toDateString()) + ['days' => count($heatmap)],
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
                $notesInRange,
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
            'suggestions' => $this->suggestions($user, $start, $end, $previousStart),
            'achievements' => $this->achievements($user, $start, $end, $previousStart),
        ];

        return $metrics + ['insights' => $this->insights->for($metrics)];
    }

    /**
     * @return array<string, mixed>
     */
    private function suggestions(User $user, Carbon $start, Carbon $end, Carbon $previousStart): array
    {
        $all = Suggestion::query()->whereBelongsTo($user)->get(['id', 'category', 'status', 'created_at', 'accepted_at', 'done_at', 'dismissed_at']);
        $in = fn (?Carbon $at, Carbon $from, Carbon $to) => $at?->between($from, $to) ?? false;
        $acted = fn (Suggestion $s, Carbon $from, Carbon $to) => $in($s->accepted_at, $from, $to) || $in($s->done_at, $from, $to);
        $dismissed = fn (Suggestion $s) => $s->status === SuggestionStatus::Dismissed && $in($s->dismissed_at, $start, $end);

        $actedInRange = $all->filter(fn (Suggestion $s) => $acted($s, $start, $end));
        $dismissedInRange = $all->filter($dismissed);
        $decided = $actedInRange->count() + $dismissedInRange->count();

        return [
            'offered' => $all->filter(fn (Suggestion $s) => $in($s->created_at, $start, $end))->count(),
            'acted' => $actedInRange->count(),
            'acted_previous' => $all->filter(fn (Suggestion $s) => $acted($s, $previousStart, $start->copy()->subSecond()))->count(),
            'accepted' => $all->filter(fn (Suggestion $s) => $in($s->accepted_at, $start, $end))->count(),
            'done' => $all->filter(fn (Suggestion $s) => $in($s->done_at, $start, $end))->count(),
            'dismissed' => $dismissedInRange->count(),
            'open_now' => $all->where('status', SuggestionStatus::New)->count(),
            'acceptance_rate' => $decided > 0 ? (int) round($actedInRange->count() / $decided * 100) : null,
            'by_category' => collect(SuggestionCategory::cases())->map(fn (SuggestionCategory $c) => [
                'key' => $c->value,
                'acted' => $actedInRange->where('category', $c)->count(),
                'dismissed' => $dismissedInRange->where('category', $c)->count(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function achievements(User $user, Carbon $start, Carbon $end, Carbon $previousStart): array
    {
        $recent = Achievement::query()->whereBelongsTo($user)->with('goal:id,title')
            ->whereDate('achieved_on', '>=', $previousStart->toDateString())
            ->whereDate('achieved_on', '<=', $end->toDateString())
            ->orderByDesc('achieved_on')
            ->orderByDesc('id')
            ->get();
        $inRange = $recent->filter(fn (Achievement $a) => $a->achieved_on->greaterThanOrEqualTo($start->copy()->startOfDay()));

        return [
            'count' => $inRange->count(),
            'previous' => $recent->count() - $inRange->count(),
            'from_goals' => $inRange->whereNotNull('goal_id')->count(),
            'by_category' => collect(SuggestionCategory::cases())->map(fn (SuggestionCategory $c) => [
                'key' => $c->value,
                'count' => $inRange->where('category', $c)->count(),
            ])->all(),
            'latest' => $inRange->take(5)->map(fn (Achievement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'achieved_on' => $a->achieved_on->toDateString(),
                'category' => $a->category?->value,
                'goal' => $a->goal ? ['id' => $a->goal->id, 'title' => $a->goal->title] : null,
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, FocusSession>  $sessions
     * @param  Collection<int, FocusSession>  $previous
     * @return array<string, mixed>
     */
    private function focusSummary(User $user, Collection $sessions, Collection $previous, int $days): array
    {
        $byHour = array_fill(0, 24, 0);
        foreach ($sessions as $session) {
            $byHour[(int) $session->started_at->format('G')] += $session->focused_seconds;
        }
        $byHour = array_map(fn (int $seconds) => (int) round($seconds / 60), $byHour);
        $minutes = FocusStats::minutes($sessions);
        $goal = $this->focus->goalMinutes($user);
        $byDay = FocusStats::minutesByDay($sessions);

        return [
            'minutes' => $minutes,
            'minutes_previous' => FocusStats::minutes($previous),
            'sessions' => $sessions->count(),
            'completed' => $sessions->where('completed', true)->count(),
            'per_day' => (int) round($minutes / $days),
            'goal_minutes' => $goal,
            'goal_days' => $goal > 0 ? count(array_filter($byDay, fn (int $m) => $m >= $goal)) : 0,
            'best_hour' => max($byHour) > 0 ? array_search(max($byHour), $byHour, true) : null,
            'by_hour' => $byHour,
        ];
    }

    /**
     * @param  Collection<int, Task>  $created
     * @param  Collection<int, Task>  $completed
     * @param  Collection<int, TaskNote>  $notes
     * @return list<array{date: string, created: int, completed: int, logged: int, minutes: int}>
     */
    private function daily(Carbon $start, int $days, Collection $created, Collection $completed, Collection $notes): array
    {
        $createdByDay = $created->countBy(fn (Task $t) => $t->created_at->toDateString());
        $completedByDay = $completed->countBy(fn (Task $t) => $t->completed_at->toDateString());
        $notesByDay = $notes->groupBy(fn (TaskNote $n) => $n->logged_on->toDateString());

        return collect(range(0, $days - 1))->map(function (int $offset) use ($start, $createdByDay, $completedByDay, $notesByDay) {
            $date = $start->copy()->addDays($offset)->toDateString();

            return [
                'date' => $date,
                'created' => $createdByDay[$date] ?? 0,
                'completed' => $completedByDay[$date] ?? 0,
                'logged' => isset($notesByDay[$date]) ? $notesByDay[$date]->count() : 0,
                'minutes' => isset($notesByDay[$date]) ? (int) $notesByDay[$date]->sum('minutes') : 0,
            ];
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
