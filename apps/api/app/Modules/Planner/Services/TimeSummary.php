<?php

namespace App\Modules\Planner\Services;

use App\Modules\Clock\Services\FocusStats;
use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Where the account's time went: the day plan's blocks that have already happened, minus the ones
 * whose task was skipped, grouped by the task's type the way a screen-time report groups apps.
 */
class TimeSummary
{
    public const RANGES = ['day', 'week', 'month'];

    public function __construct(private readonly FocusStats $focus) {}

    /** @return array<string, mixed> */
    public function for(User $user, string $range, Carbon $date): array
    {
        [$from, $to] = $this->period($range, $date);
        [$prevFrom, $prevTo] = $this->period($range, $this->previous($range, $from));

        $spans = $this->spans($user, $from, $to);
        $total = $spans->sum('minutes');
        $elapsed = max(1, (int) $from->diffInDays(min($to, today())) + 1);

        return [
            'range' => $range,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total' => $total,
            'previous_total' => $previous = $this->spans($user, $prevFrom, $prevTo)->sum('minutes'),
            'daily_average' => (int) round($total / $elapsed),
            'previous_average' => (int) round($previous / ((int) $prevFrom->diffInDays($prevTo) + 1)),
            'focus_minutes' => FocusStats::minutes($this->focus->between($user, $from, $to->copy()->endOfDay())),
            'categories' => $this->categories($spans),
            'buckets' => $range === 'day' ? $this->byHour($spans) : $this->byDay($spans, $from, $to),
            'activities' => $spans->groupBy('title')
                ->map(fn (Collection $rows, string $title) => [
                    'title' => $title,
                    'key' => $rows->first()['key'],
                    'minutes' => $rows->sum('minutes'),
                    'days' => $rows->pluck('date')->unique()->count(),
                ])
                ->sortByDesc('minutes')->take(8)->values()->all(),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(string $range, Carbon $date): array
    {
        return match ($range) {
            'day' => [$date->copy()->startOfDay(), $date->copy()->startOfDay()],
            'week' => [$date->copy()->startOfWeek(Carbon::MONDAY), $date->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay()],
            default => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()->startOfDay()],
        };
    }

    private function previous(string $range, Carbon $from): Carbon
    {
        return match ($range) {
            'day' => $from->copy()->subDay(),
            'week' => $from->copy()->subWeek(),
            default => $from->copy()->subMonthNoOverflow(),
        };
    }

    /**
     * Each block as the minutes it actually covered, clipped to now on today and dropped after it.
     *
     * @return Collection<int, array{date: string, start: int, minutes: int, title: string, key: string, name: string, tone: string|null}>
     */
    private function spans(User $user, Carbon $from, Carbon $to): Collection
    {
        $today = today()->toDateString();
        $nowMinute = now()->hour * 60 + now()->minute;

        return TimeBlock::query()
            ->whereBelongsTo($user)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', min($to, today())->toDateString())
            ->with('task:id,title,status,task_type_id', 'task.type:id,name,tone')
            ->get()
            ->reject(fn (TimeBlock $block) => $block->task?->status === TaskStatus::Cancelled)
            ->map(function (TimeBlock $block) use ($today, $nowMinute) {
                $date = $block->date->toDateString();
                $minutes = $date === $today
                    ? max(0, min($block->minutes, $nowMinute - $block->start_minute))
                    : $block->minutes;
                $type = $block->task?->type;

                return [
                    'date' => $date,
                    'start' => $block->start_minute,
                    'minutes' => $minutes,
                    'title' => $block->task?->title ?? $block->title ?? 'Untitled',
                    'key' => $type ? (string) $type->id : 'other',
                    'name' => $type?->name ?? 'Other',
                    'tone' => $type?->tone,
                ];
            })
            ->filter(fn (array $span) => $span['minutes'] > 0)
            ->values();
    }

    /** @return list<array{key: string, name: string, tone: string|null, minutes: int}> */
    private function categories(Collection $spans): array
    {
        return $spans->groupBy('key')
            ->map(fn (Collection $rows, string $key) => [
                'key' => $key,
                'name' => $rows->first()['name'],
                'tone' => $rows->first()['tone'],
                'minutes' => $rows->sum('minutes'),
            ])
            ->sortByDesc('minutes')->values()->all();
    }

    /** @return list<array{label: string, minutes: array<string, int>}> */
    private function byHour(Collection $spans): array
    {
        $hours = array_fill(0, 24, []);
        foreach ($spans as $span) {
            for ($m = $span['start']; $m < $span['start'] + $span['minutes'];) {
                $hour = intdiv($m, 60);
                $take = min(($hour + 1) * 60, $span['start'] + $span['minutes']) - $m;
                $hours[$hour][$span['key']] = ($hours[$hour][$span['key']] ?? 0) + $take;
                $m += $take;
            }
        }

        return array_map(fn (int $hour) => ['label' => (string) $hour, 'minutes' => $hours[$hour]], range(0, 23));
    }

    /** @return list<array{label: string, minutes: array<string, int>}> */
    private function byDay(Collection $spans, Carbon $from, Carbon $to): array
    {
        $days = $spans->groupBy('date');
        $out = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $rows = $days->get($day->toDateString(), collect());
            $out[] = [
                'label' => $day->toDateString(),
                'minutes' => $rows->groupBy('key')->map(fn (Collection $r) => $r->sum('minutes'))->all(),
            ];
        }

        return $out;
    }
}
