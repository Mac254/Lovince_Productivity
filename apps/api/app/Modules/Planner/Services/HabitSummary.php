<?php

namespace App\Modules\Planner\Services;

use App\Modules\Planner\Models\ScheduleItem;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HabitSummary
{
    public const DAYS = [14, 30, 90];

    /**
     * Each habit a schedule lays out: how often it was done over the last few days, its streaks
     * counted over the days it was planned, and the day-by-day result.
     *
     * @return array<string, mixed>
     */
    public function for(User $user, int $days): array
    {
        $today = today();
        $from = $today->copy()->subDays($days - 1);
        $current = ScheduleItem::query()
            ->whereIn('schedule_id', $user->schedules()->select('id'))
            ->get(['title', 'alternatives'])
            ->keyBy('title');

        $habits = Task::query()->whereBelongsTo($user)
            ->whereNotNull('habit')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->toDateString())
            ->with('type:id,name,tone')
            ->orderBy('due_date')
            ->get()
            ->groupBy('habit')
            ->map(function (Collection $tasks, string $habit) use ($from, $today, $current) {
                $byDay = $tasks->groupBy(fn (Task $task) => $task->due_date->toDateString())
                    ->map(fn (Collection $day) => $this->status($day, $today));
                $inRange = $byDay->filter(fn (string $status, string $date) => $date >= $from->toDateString());
                if ($inRange->isEmpty()) {
                    return null;
                }

                $settled = $byDay->reject(fn (string $status) => $status === 'open');
                $done = $inRange->filter(fn (string $status) => $this->counts($status))->count();
                $planned = $inRange->reject(fn (string $status) => $status === 'open')->count();
                $latest = $tasks->last();

                return [
                    'title' => $habit,
                    'type' => $latest->type ? ['id' => $latest->type->id, 'name' => $latest->type->name, 'tone' => $latest->type->tone] : null,
                    'scheduled' => $current->has($habit),
                    'alternatives' => $current->get($habit)?->alternatives ?? [],
                    'done' => $done,
                    'planned' => $planned,
                    'rate' => $planned ? (int) round($done / $planned * 100) : null,
                    'streak' => $this->streak($settled->reverse()->values(), $byDay->last() === 'open' ? 0 : null),
                    'best_streak' => $this->best($settled->values()),
                    'today' => $byDay->get($today->toDateString()),
                    'last_done' => $tasks->filter(fn (Task $task) => $task->status === TaskStatus::Completed)->last()?->title,
                    'days' => $inRange->map(fn (string $status, string $date) => ['date' => $date, 'status' => $status])->values(),
                ];
            })
            ->filter()
            ->sortBy([['scheduled', 'desc'], ['planned', 'desc'], ['title', 'asc']])
            ->values();

        return [
            'days' => $days,
            'from' => $from->toDateString(),
            'to' => $today->toDateString(),
            'habits' => $habits,
        ];
    }

    /** One day's result, from the habit's task (or tasks) due that day. */
    private function status(Collection $tasks, Carbon $today): string
    {
        $completed = $tasks->first(fn (Task $task) => $task->status === TaskStatus::Completed);
        if ($completed) {
            return $completed->fallback_done ? 'short' : 'done';
        }
        if ($tasks->every(fn (Task $task) => $task->status === TaskStatus::Cancelled)) {
            return 'skipped';
        }

        return $tasks->first()->due_date->isSameDay($today) ? 'open' : 'missed';
    }

    private function counts(string $status): bool
    {
        return $status === 'done' || $status === 'short';
    }

    /** Days in a row it was done, newest first; a day still open today doesn't break it. */
    private function streak(Collection $statuses, ?int $start): int
    {
        $streak = $start ?? 0;
        foreach ($statuses as $status) {
            if (! $this->counts($status)) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    private function best(Collection $statuses): int
    {
        $best = 0;
        $run = 0;
        foreach ($statuses as $status) {
            $run = $this->counts($status) ? $run + 1 : 0;
            $best = max($best, $run);
        }

        return $best;
    }
}
