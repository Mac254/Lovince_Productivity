<?php

namespace App\Modules\Metrics\Services;

use App\Modules\Clock\Services\FocusStats;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;

class ActivityDays
{
    public const FOCUS_UNIT_MINUTES = 25;

    public const LOOKBACK_DAYS = 366;

    public function __construct(private readonly FocusStats $focus) {}

    /**
     * @return list<array{date: string, count: int, completed: int, logged: int, focus_minutes: int}>
     */
    public function between(User $user, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $completed = Task::query()->whereBelongsTo($user)
            ->where('status', TaskStatus::Completed)
            ->where('completed_at', '>=', $from)
            ->where('completed_at', '<', $to->copy()->addDay())
            ->pluck('completed_at')
            ->countBy(fn ($at) => Carbon::parse($at)->toDateString());
        $logged = TaskNote::query()->whereBelongsTo($user)
            ->whereDate('logged_on', '>=', $from->toDateString())
            ->whereDate('logged_on', '<=', $to->toDateString())
            ->pluck('logged_on')
            ->countBy(fn ($on) => Carbon::parse($on)->toDateString());
        $focus = FocusStats::minutesByDay($this->focus->between($user, $from, $to));

        return collect(range(0, (int) $from->diffInDays($to)))->map(function (int $offset) use ($from, $completed, $logged, $focus) {
            $date = $from->copy()->addDays($offset)->toDateString();
            $done = $completed[$date] ?? 0;
            $notes = $logged[$date] ?? 0;
            $minutes = $focus[$date] ?? 0;

            return [
                'date' => $date,
                'count' => $done + $notes + (int) ceil($minutes / self::FOCUS_UNIT_MINUTES),
                'completed' => $done,
                'logged' => $notes,
                'focus_minutes' => $minutes,
            ];
        })->all();
    }

    /**
     * @param  list<array{date: string, count: int}>  $days
     * @return array{current: int, best: int, active_today: bool, active_days: int}
     */
    public static function streaks(array $days, string $today): array
    {
        $active = array_map(fn (array $day) => $day['count'] > 0, array_values(array_filter($days, fn (array $day) => $day['date'] <= $today)));
        $best = 0;
        $run = 0;
        foreach ($active as $on) {
            $run = $on ? $run + 1 : 0;
            $best = max($best, $run);
        }

        $activeToday = (bool) end($active);
        $current = 0;
        for ($i = count($active) - ($activeToday ? 1 : 2); $i >= 0 && $active[$i]; $i--) {
            $current++;
        }

        return ['current' => $current, 'best' => $best, 'active_today' => $activeToday, 'active_days' => count(array_filter($active))];
    }

    /**
     * @return array{current: int, best: int, active_today: bool, active_days: int}
     */
    public function streak(User $user): array
    {
        return self::streaks($this->between($user, today()->subDays(self::LOOKBACK_DAYS), today()), today()->toDateString());
    }
}
