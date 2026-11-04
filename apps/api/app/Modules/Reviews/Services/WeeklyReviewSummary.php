<?php

namespace App\Modules\Reviews\Services;

use App\Modules\Blockers\Models\Blocker;
use App\Modules\Clock\Services\FocusStats;
use App\Modules\Goals\Enums\GoalStatus;
use App\Modules\Goals\Models\Goal;
use App\Modules\Journal\Models\JournalEntry;
use App\Modules\Reviews\Models\WeeklyReview;
use App\Modules\Tasks\Http\Resources\TaskResource;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class WeeklyReviewSummary
{
    private const LIST_LIMIT = 25;

    public function __construct(private readonly FocusStats $focus) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user, Carbon $weekStart): array
    {
        $start = $weekStart->copy()->startOfDay();
        $end = $start->copy()->endOfWeek();
        $nextStart = $start->copy()->addWeek();
        $nextEnd = $nextStart->copy()->endOfWeek();

        $tasks = fn () => Task::query()->whereBelongsTo($user)->with(Task::RELATIONS)->withSum('notes', 'minutes');

        $completed = $tasks()->whereBetween('completed_at', [$start, $end])->orderBy('completed_at')->get();
        $carryOver = $tasks()->open()->whereDate('due_date', '<=', $end->toDateString())->orderBy('due_date')->limit(self::LIST_LIMIT)->get();
        $nextWeek = $tasks()->open()->whereDate('due_date', '>=', $nextStart)->whereDate('due_date', '<=', $nextEnd)->orderBy('due_date')->limit(self::LIST_LIMIT)->get();
        $unscheduled = $tasks()->open()->whereNull('due_date')->orderByPriority()->limit(self::LIST_LIMIT)->get();

        $notes = TaskNote::query()->whereBelongsTo($user)->whereDate('logged_on', '>=', $start)->whereDate('logged_on', '<=', $end)->get(['logged_on', 'minutes']);
        $focus = $this->focus->between($user, $start, $end);
        $focusByDay = FocusStats::minutesByDay($focus);
        $loggedByDay = $notes->groupBy(fn (TaskNote $note) => $note->logged_on->toDateString())->map->sum('minutes');
        $completedByDay = $completed->countBy(fn (Task $task) => $task->completed_at->toDateString());

        $review = WeeklyReview::query()->whereBelongsTo($user)->whereDate('week_start', $start->toDateString())->first();

        return [
            'week' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'next_start' => $nextStart->toDateString(), 'next_end' => $nextEnd->toDateString()],
            'totals' => [
                'completed' => $completed->count(),
                'minutes_logged' => (int) $notes->sum('minutes'),
                'focus_minutes' => FocusStats::minutes($focus),
                'focus_sessions' => $focus->count(),
                'blockers_opened' => Blocker::query()->whereBelongsTo($user)->whereBetween('created_at', [$start, $end])->count(),
                'blockers_resolved' => Blocker::query()->whereBelongsTo($user)->whereBetween('resolved_at', [$start, $end])->count(),
                'blockers_open' => Blocker::query()->whereBelongsTo($user)->open()->count(),
            ],
            'days' => collect(range(0, 6))->map(function (int $offset) use ($start, $focusByDay, $loggedByDay, $completedByDay) {
                $date = $start->copy()->addDays($offset)->toDateString();

                return [
                    'date' => $date,
                    'completed' => $completedByDay[$date] ?? 0,
                    'minutes_logged' => (int) ($loggedByDay[$date] ?? 0),
                    'focus_minutes' => $focusByDay[$date] ?? 0,
                ];
            })->all(),
            'completed' => TaskResource::collection($completed)->resolve(),
            'carry_over' => TaskResource::collection($carryOver)->resolve(),
            'next_week' => TaskResource::collection($nextWeek)->resolve(),
            'unscheduled' => TaskResource::collection($unscheduled)->resolve(),
            'goals' => Goal::query()->whereBelongsTo($user)->where('status', GoalStatus::Active)->withProgress()->orderBy('target_date')->get()
                ->map(fn (Goal $goal) => [
                    'id' => $goal->id,
                    'title' => $goal->title,
                    'tone' => $goal->tone,
                    'progress' => $goal->progress(),
                    'completed' => (int) $goal->completed_tasks_count,
                    'total' => (int) $goal->eligible_tasks_count,
                    'completed_this_week' => $completed->where('goal_id', $goal->id)->count(),
                ])->all(),
            'journal' => JournalEntry::query()->whereBelongsTo($user)->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)->orderBy('date')->get()
                ->map(fn (JournalEntry $entry) => ['date' => $entry->date->toDateString(), 'excerpt' => Str::limit(trim(strip_tags($entry->body)), 160)])->all(),
            'review' => $review ? [
                'wins' => $review->wins,
                'challenges' => $review->challenges,
                'next_focus' => $review->next_focus,
                'completed_at' => $review->completed_at?->toIso8601String(),
                'updated_at' => $review->updated_at?->toIso8601String(),
            ] : null,
        ];
    }
}
