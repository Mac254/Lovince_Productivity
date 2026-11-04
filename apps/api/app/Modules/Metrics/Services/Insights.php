<?php

namespace App\Modules\Metrics\Services;

use Illuminate\Support\Carbon;

class Insights
{
    private const MIN_SAMPLE = 5;

    /**
     * @param  array<string, mixed>  $metrics
     * @return list<array{key: string, tone: 'positive'|'neutral'|'warning', text: string}>
     */
    public function for(array $metrics): array
    {
        $totals = $metrics['totals'];
        $focus = $metrics['focus'];
        $days = $metrics['range']['days'];

        return array_values(array_filter([
            $this->overdue($totals['overdue_now']),
            $this->pace($totals['completed'], $totals['completed_previous'], $days),
            $this->onTime($totals['on_time_rate'], $totals['on_time_sample']),
            $this->estimates($totals['estimate_accuracy'], $totals['estimated_tasks']),
            $this->streak($metrics['consistency']['current']),
            $this->bestHour($focus['best_hour'], $focus['sessions']),
            $this->bestWeekday($metrics['daily'], $totals['completed'], $days),
            $this->topCategory($metrics['by_category'], $totals['completed']),
            $this->suggestions($metrics['suggestions'] ?? null),
            $this->achievements($metrics['achievements'] ?? null, $days),
        ]));
    }

    private function overdue(int $overdue): ?array
    {
        return $overdue > 0
            ? $this->insight('overdue', 'warning', $overdue === 1 ? '1 open task is overdue.' : "{$overdue} open tasks are overdue.")
            : null;
    }

    private function pace(int $completed, int $previous, int $days): ?array
    {
        if ($previous < self::MIN_SAMPLE && $completed < self::MIN_SAMPLE) {
            return null;
        }
        if ($previous === 0) {
            return $this->insight('pace', 'positive', "You finished {$completed} tasks in the last {$days} days, up from none before.");
        }
        $change = (int) round(($completed - $previous) / $previous * 100);
        if (abs($change) < 10) {
            return $this->insight('pace', 'neutral', "You’re finishing tasks at the same pace as the {$days} days before.");
        }

        return $change > 0
            ? $this->insight('pace', 'positive', "You finished {$change}% more tasks than in the {$days} days before.")
            : $this->insight('pace', 'warning', 'You finished '.abs($change)."% fewer tasks than in the {$days} days before.");
    }

    private function onTime(?int $rate, int $sample): ?array
    {
        if ($rate === null || $sample < self::MIN_SAMPLE) {
            return null;
        }

        return $this->insight('on_time', $rate >= 80 ? 'positive' : ($rate < 60 ? 'warning' : 'neutral'), "{$rate}% of tasks with a due date were done on time.");
    }

    private function estimates(?int $accuracy, int $sample): ?array
    {
        if ($accuracy === null || $sample < 3) {
            return null;
        }
        if ($accuracy > 115) {
            return $this->insight('estimates', 'warning', 'Tasks take about '.($accuracy - 100).'% longer than you estimate.');
        }
        if ($accuracy < 85) {
            return $this->insight('estimates', 'neutral', 'Tasks take about '.(100 - $accuracy).'% less time than you estimate.');
        }

        return $this->insight('estimates', 'positive', 'Your time estimates are on target.');
    }

    private function streak(int $streak): ?array
    {
        return $streak >= 3 ? $this->insight('streak', 'positive', "{$streak}-day streak. Keep it going.") : null;
    }

    private function bestHour(?int $hour, int $sessions): ?array
    {
        return $hour !== null && $sessions >= 3
            ? $this->insight('best_hour', 'neutral', sprintf('Your focus peaks around %02d:00.', $hour))
            : null;
    }

    /**
     * @param  list<array{date: string, completed: int}>  $daily
     */
    private function bestWeekday(array $daily, int $completed, int $days): ?array
    {
        if ($completed < self::MIN_SAMPLE * 2 || $days < 14) {
            return null;
        }
        $byWeekday = collect($daily)->groupBy(fn (array $day) => Carbon::parse($day['date'])->dayOfWeekIso)->map->sum('completed')->sortDesc();
        if ($byWeekday->values()->get(1) === $byWeekday->first()) {
            return null;
        }
        $best = $byWeekday->keys()->first();

        return $this->insight('weekday', 'neutral', 'You finish the most on '.Carbon::create()->isoWeekday($best)->englishDayOfWeek.'s.');
    }

    /**
     * @param  list<array{key: string, completed: int}>  $categories
     */
    private function topCategory(array $categories, int $completed): ?array
    {
        if ($completed < self::MIN_SAMPLE) {
            return null;
        }
        $top = collect($categories)->sortByDesc('completed')->first();
        $share = (int) round($top['completed'] / $completed * 100);

        return $share >= 50
            ? $this->insight('category', 'neutral', "{$share}% of what you finish is ".ucfirst($top['key']).' work.')
            : null;
    }

    /**
     * @param  array{acted: int, dismissed: int, acceptance_rate: int|null}|null  $suggestions
     */
    private function suggestions(?array $suggestions): ?array
    {
        if ($suggestions === null || $suggestions['acceptance_rate'] === null || $suggestions['acted'] + $suggestions['dismissed'] < 3) {
            return null;
        }
        $rate = $suggestions['acceptance_rate'];

        return $this->insight('suggestions', $rate >= 50 ? 'positive' : 'neutral', "You acted on {$suggestions['acted']} suggestions, {$rate}% of the ones you decided on.");
    }

    /**
     * @param  array{count: int, previous: int}|null  $achievements
     */
    private function achievements(?array $achievements, int $days): ?array
    {
        if ($achievements === null || $achievements['count'] === 0) {
            return null;
        }
        $count = $achievements['count'];

        return $this->insight('achievements', 'positive', ($count === 1 ? '1 achievement' : "{$count} achievements")." recorded in the last {$days} days.");
    }

    /**
     * @param  'positive'|'neutral'|'warning'  $tone
     * @return array{key: string, tone: 'positive'|'neutral'|'warning', text: string}
     */
    private function insight(string $key, string $tone, string $text): array
    {
        return ['key' => $key, 'tone' => $tone, 'text' => $text];
    }
}
