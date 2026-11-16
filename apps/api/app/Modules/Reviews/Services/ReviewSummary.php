<?php

namespace App\Modules\Reviews\Services;

use App\Modules\Blockers\Models\Blocker;
use App\Modules\Clock\Services\FocusStats;
use App\Modules\Goals\Enums\GoalStatus;
use App\Modules\Goals\Models\Goal;
use App\Modules\Journal\Models\JournalEntry;
use App\Modules\Reviews\Enums\ReviewPeriod;
use App\Modules\Reviews\Models\Review;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Http\Resources\TaskResource;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What a week, month or year held: totals, a bar per day (per month for a year), the tasks done, carried over,
 * skipped and coming next, goal progress, journal entries, the user's reflection, and the shorter reviews written
 * inside it (a month's weeks, a year's months).
 */
class ReviewSummary
{
    private const LIST_LIMIT = 25;

    /** A month or year can hold thousands of tasks; the page lists the latest and counts the rest. */
    private const COMPLETED_LIMIT = 100;

    private const JOURNAL_LIMIT = 31;

    public function __construct(private readonly FocusStats $focus) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user, ReviewPeriod $period, Carbon $start): array
    {
        $start = $start->copy()->startOfDay();
        $end = $period->end($start);
        $nextStart = $period->next($start);
        $nextEnd = $period->end($nextStart);
        $isWeek = $period === ReviewPeriod::Week;

        $tasks = fn () => Task::query()->whereBelongsTo($user)->with(Task::RELATIONS)->withSum('notes', 'minutes');
        $doneInPeriod = fn () => Task::query()->whereBelongsTo($user)->where('status', TaskStatus::Completed)->whereBetween('completed_at', [$start, $end]);

        $completed = $isWeek
            ? $tasks()->whereBetween('completed_at', [$start, $end])->orderBy('completed_at')->get()
            : $tasks()->whereBetween('completed_at', [$start, $end])->orderByDesc('completed_at')->limit(self::COMPLETED_LIMIT)->get();
        $carryOver = $tasks()->open()->whereDate('due_date', '<=', $end->toDateString())->orderBy('due_date')->limit(self::LIST_LIMIT)->get();
        $next = $tasks()->open()->whereDate('due_date', '>=', $nextStart)->whereDate('due_date', '<=', $nextEnd)->orderBy('due_date')->limit(self::LIST_LIMIT)->get();
        $skipped = $tasks()->where('status', TaskStatus::Cancelled)->whereDate('due_date', '>=', $start)->whereDate('due_date', '<=', $end->toDateString())->orderBy('due_date')->limit(self::LIST_LIMIT)->get();
        $unscheduled = $tasks()->open()->whereNull('due_date')->orderByPriority()->limit(self::LIST_LIMIT)->get();

        $notes = TaskNote::query()->whereBelongsTo($user)->whereDate('logged_on', '>=', $start)->whereDate('logged_on', '<=', $end)->get(['logged_on', 'minutes']);
        $focus = $this->focus->between($user, $start, $end);
        $completedDates = $doneInPeriod()->pluck('completed_at')->map(fn ($at) => Carbon::parse($at)->toDateString());
        $byGoal = $doneInPeriod()->whereNotNull('goal_id')->selectRaw('goal_id, count(*) as done')->groupBy('goal_id')->pluck('done', 'goal_id');

        $review = Review::query()->whereBelongsTo($user)->for($period, $start)->first();

        return [
            'period' => [
                'kind' => $period->value,
                'label' => $period->label($start),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'next_start' => $nextStart->toDateString(),
                'next_end' => $nextEnd->toDateString(),
            ],
            'totals' => [
                'completed' => $completedDates->count(),
                'minutes_logged' => (int) $notes->sum('minutes'),
                'focus_minutes' => FocusStats::minutes($focus),
                'focus_sessions' => $focus->count(),
                'blockers_opened' => Blocker::query()->whereBelongsTo($user)->whereBetween('created_at', [$start, $end])->count(),
                'blockers_resolved' => Blocker::query()->whereBelongsTo($user)->whereBetween('resolved_at', [$start, $end])->count(),
                'blockers_open' => Blocker::query()->whereBelongsTo($user)->open()->count(),
            ],
            'buckets' => $this->buckets(
                $period,
                $start,
                $end,
                collect(FocusStats::minutesByDay($focus)),
                $notes->groupBy(fn (TaskNote $note) => $note->logged_on->toDateString())->map->sum('minutes'),
                $completedDates->countBy(),
            ),
            'completed' => TaskResource::collection($completed)->resolve(),
            'carry_over' => TaskResource::collection($carryOver)->resolve(),
            'skipped' => TaskResource::collection($skipped)->resolve(),
            'next' => TaskResource::collection($next)->resolve(),
            'unscheduled' => TaskResource::collection($unscheduled)->resolve(),
            'goals' => Goal::query()->whereBelongsTo($user)->where('status', GoalStatus::Active)->withProgress()->orderBy('target_date')->get()
                ->map(fn (Goal $goal) => [
                    'id' => $goal->id,
                    'title' => $goal->title,
                    'tone' => $goal->tone,
                    'progress' => $goal->progress(),
                    'completed' => (int) $goal->completed_tasks_count,
                    'total' => (int) $goal->eligible_tasks_count,
                    'completed_in_period' => (int) ($byGoal[$goal->id] ?? 0),
                ])->all(),
            'journal' => JournalEntry::query()->whereBelongsTo($user)->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)
                ->when($isWeek, fn ($q) => $q->orderBy('date'), fn ($q) => $q->orderByDesc('date')->limit(self::JOURNAL_LIMIT))->get()
                ->map(fn (JournalEntry $entry) => ['date' => $entry->date->toDateString(), 'excerpt' => Str::limit(trim(strip_tags($entry->body)), 160)])->all(),
            'review' => $review ? $this->reflection($review) : null,
            'shorter' => $this->shorter($user, $period, $start, $end),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reflection(Review $review): array
    {
        return [
            'wins' => $review->wins,
            'challenges' => $review->challenges,
            'next_focus' => $review->next_focus,
            'completed_at' => $review->completed_at?->toIso8601String(),
            'updated_at' => $review->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The reviews this one builds on, oldest first: every week that starts in the month, every month of the year.
     *
     * @return list<array<string, mixed>>
     */
    private function shorter(User $user, ReviewPeriod $period, Carbon $start, Carbon $end): array
    {
        $shorter = $period->shorter();
        if ($shorter === null) {
            return [];
        }

        return Review::query()->whereBelongsTo($user)->where('period', $shorter)
            ->whereDate('starts_on', '>=', $start->toDateString())->whereDate('starts_on', '<=', $end->toDateString())
            ->orderBy('starts_on')->get()
            ->map(fn (Review $review) => [
                'period' => $shorter->value,
                'start' => $review->starts_on->toDateString(),
                'label' => $review->label(),
                ...$this->reflection($review),
            ])->values()->all();
    }

    /**
     * One bar per day for a week or month, one per month for a year.
     *
     * @param  Collection<string, int>  $focus  minutes by date
     * @param  Collection<string, int|float>  $logged  minutes by date
     * @param  Collection<string, int>  $done  completed tasks by date
     * @return list<array{start: string, end: string, completed: int, minutes_logged: int, focus_minutes: int}>
     */
    private function buckets(ReviewPeriod $period, Carbon $start, Carbon $end, Collection $focus, Collection $logged, Collection $done): array
    {
        $byMonth = $period === ReviewPeriod::Year;
        $key = fn (string $date) => $byMonth ? substr($date, 0, 7) : $date;
        $sum = fn (Collection $values) => $values->groupBy(fn ($value, string $date) => $key($date), true)->map->sum();
        [$focus, $logged, $done] = [$sum($focus), $sum($logged), $sum($done)];

        $buckets = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $byMonth ? $cursor->addMonthNoOverflow() : $cursor->addDay()) {
            $id = $key($cursor->toDateString());
            $buckets[] = [
                'start' => $cursor->toDateString(),
                'end' => ($byMonth ? $cursor->copy()->endOfMonth() : $cursor)->toDateString(),
                'completed' => (int) ($done[$id] ?? 0),
                'minutes_logged' => (int) ($logged[$id] ?? 0),
                'focus_minutes' => (int) ($focus[$id] ?? 0),
            ];
        }

        return $buckets;
    }
}
