<?php

namespace App\Services;

use App\Enums\GoalStatus;
use App\Enums\GoalTimeframe;
use App\Enums\TaskCategory;
use App\Enums\TaskStatus;
use App\Http\Resources\BlockerResource;
use App\Http\Resources\GoalResource;
use App\Http\Resources\QuoteResource;
use App\Http\Resources\TaskResource;
use App\Models\Blocker;
use App\Models\Goal;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Support\TypeBreakdown;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the dashboard summary purely from persisted data.
 */
class DashboardSummary
{
    private const LIST_LIMIT = 8;

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $today = today();
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();

        $tasks = fn (): Builder => Task::query()->whereBelongsTo($user)->with(Task::RELATIONS);

        $dueThisWeek = $tasks()
            ->whereBetween('due_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('status', '!=', TaskStatus::Cancelled);
        $weekTotal = (clone $dueThisWeek)->count();

        // Today's plan: open work due today plus anything starred to work on now.
        $planned = fn (): Builder => $tasks()->open()->where(
            fn (Builder $q) => $q->whereDate('due_date', $today)->orWhere('is_highlighted', true)
        );
        $weekCompleted = (clone $dueThisWeek)->where('status', TaskStatus::Completed)->count();

        return [
            'date' => $today->toDateString(),
            'week' => [
                'start' => $weekStart->toDateString(),
                'end' => $weekEnd->toDateString(),
            ],
            'stats' => [
                'due_today' => $tasks()->open()->whereDate('due_date', $today)->count(),
                'planned_today' => $planned()->count(),
                // Done today and part of the plan (due today or starred): the plan's numerator everywhere.
                'planned_completed_today' => $tasks()->where('completed_at', '>=', $today)
                    ->where(fn (Builder $q) => $q->whereDate('due_date', $today)->orWhere('is_highlighted', true))
                    ->count(),
                // Still open but worked on today (a work log entry dated today): half a step of progress.
                'planned_worked_today' => $planned()
                    ->whereHas('notes', fn (Builder $q) => $q->whereDate('logged_on', $today))
                    ->count(),
                'overdue' => $tasks()->open()->whereDate('due_date', '<', $today)->count(),
                'in_progress' => $tasks()->where('status', TaskStatus::InProgress)->count(),
                'open' => $tasks()->open()->count(),
                'completed_today' => $tasks()->where('completed_at', '>=', $today)->count(),
                'completed_this_week' => $tasks()->where('completed_at', '>=', $weekStart)->count(),
                'completed_total' => $tasks()->where('status', TaskStatus::Completed)->count(),
                'active_goals' => Goal::query()->whereBelongsTo($user)->where('status', GoalStatus::Active)->count(),
                'open_blockers' => Blocker::query()->whereBelongsTo($user)->open()->count(),
                'week_due_total' => $weekTotal,
                'week_due_completed' => $weekCompleted,
                // Null when nothing is due this week, so the UI never shows a misleading 0% or 100%.
                'weekly_completion_rate' => $weekTotal > 0 ? (int) round($weekCompleted / $weekTotal * 100) : null,
            ],
            'today_tasks' => TaskResource::collection(
                $tasks()->open()->whereDate('due_date', $today)->orderByPriority()->get()
            ),
            'planned_tasks' => TaskResource::collection(
                $planned()->orderByPriority()->orderBy('due_date')->get()
            ),
            'overdue_tasks' => TaskResource::collection(
                $tasks()->open()->whereDate('due_date', '<', $today)->orderBy('due_date')->limit(self::LIST_LIMIT)->get()
            ),
            'completed_today_tasks' => TaskResource::collection(
                $tasks()->where('completed_at', '>=', $today)->latest('completed_at')->get()
            ),
            'upcoming_tasks' => TaskResource::collection(
                $tasks()->open()
                    ->whereDate('due_date', '>', $today)
                    ->whereDate('due_date', '<=', $today->copy()->addDays(14))
                    ->orderBy('due_date')
                    ->limit(self::LIST_LIMIT)
                    ->get()
            ),
            'highlighted_tasks' => TaskResource::collection(
                $tasks()->where('is_highlighted', true)->where('status', '!=', TaskStatus::Cancelled)
                    ->orderByRaw("CASE WHEN status = 'completed' THEN 1 ELSE 0 END")
                    ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('due_date')
                    ->limit(self::LIST_LIMIT)
                    ->get()
            ),
            'open_blockers' => BlockerResource::collection(
                Blocker::query()->whereBelongsTo($user)->open()->with('task:id,title')->latest()->limit(5)->get()
            ),
            'recent_activity' => TaskResource::collection(
                $tasks()->latest('updated_at')->limit(self::LIST_LIMIT)->get()
            ),
            'by_type' => TypeBreakdown::rows(
                $user,
                $tasks()->where('completed_at', '>=', $weekStart)->get(),
                $tasks()->open()->get(),
            ),
            'integration' => $this->integrationSummary($user),
            'weekly_goals' => GoalResource::collection(
                Goal::query()
                    ->whereBelongsTo($user)
                    ->where('timeframe', GoalTimeframe::Weekly)
                    ->where('status', GoalStatus::Active)
                    ->withProgress()
                    ->orderBy('target_date')
                    ->get()
            ),
            'active_goals' => GoalResource::collection(
                Goal::query()
                    ->whereBelongsTo($user)
                    ->where('status', GoalStatus::Active)
                    ->withProgress()
                    ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('target_date')
                    ->limit(5)
                    ->get()
            ),
            'quote' => ($quote = Quote::pickRandom()) ? QuoteResource::make($quote)->resolve() : null,
        ];
    }

    /**
     * Development and integration are separate deliverables: finished development
     * stays finished while its integration is tracked on its own.
     *
     * @return array<string, mixed>
     */
    private function integrationSummary(User $user): array
    {
        $base = fn (): Builder => Task::query()->whereBelongsTo($user);

        return [
            'development_completed' => $base()->where('category', TaskCategory::Development)->where('status', TaskStatus::Completed)->count(),
            'development_open' => $base()->where('category', TaskCategory::Development)->open()->count(),
            'integration_completed' => $base()->where('category', TaskCategory::Integration)->where('status', TaskStatus::Completed)->count(),
            'integration_pending' => TaskResource::collection(
                $base()
                    ->with(Task::RELATIONS)
                    ->where('category', TaskCategory::Integration)
                    ->open()
                    ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('due_date')
                    ->limit(self::LIST_LIMIT)
                    ->get()
            ),
        ];
    }
}
