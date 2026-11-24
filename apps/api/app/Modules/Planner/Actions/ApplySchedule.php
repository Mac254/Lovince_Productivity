<?php

namespace App\Modules\Planner\Actions;

use App\Modules\Planner\Models\Schedule;
use App\Modules\Planner\Models\ScheduleItem;
use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplySchedule
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * Lays a schedule onto a day: each item becomes a habit task due that day (reusing the one already
     * there; a slot with alternatives takes the one done least recently) and a time block, carrying the item's short version onto the task. Existing blocks either make it fail on overlap or are replaced.
     *
     * @return Collection<int, TimeBlock>
     */
    public function __invoke(Schedule $schedule, string $date, bool $replace = false): Collection
    {
        $user = $schedule->user;
        $items = $schedule->items;

        if ($date > today()->toDateString() && ! $this->settings->resolve($user->settings)['planner']['plan_ahead']) {
            throw ValidationException::withMessages([
                'date' => 'Schedules only fill today. Turn on “Plan days ahead” in Settings to plan a later day.',
            ]);
        }

        return DB::transaction(function () use ($user, $items, $date, $replace) {
            $existing = TimeBlock::query()->whereBelongsTo($user)->whereDate('date', $date);

            if ($replace) {
                $existing->delete();
            } else {
                $clash = $existing->get()->first(fn (TimeBlock $block) => $items->contains(
                    fn ($item) => $item->start_minute < $block->start_minute + $block->minutes
                        && $block->start_minute < $item->start_minute + $item->minutes
                ));
                if ($clash) {
                    throw ValidationException::withMessages([
                        'date' => "“{$clash->title}” is already planned at that time. Replace the day's plan to use this schedule.",
                    ]);
                }
            }

            return $items->map(function (ScheduleItem $item) use ($user, $date) {
                $task = Task::query()->whereBelongsTo($user)->whereDate('due_date', $date)
                    ->where(fn ($query) => $query->where('habit', $item->title)->orWhere('title', $item->title))
                    ->first();
                $title = $task->title ?? $this->pick($item, $user, $date);
                $task ??= $user->tasks()->create([
                    'title' => $title,
                    'habit' => $item->title,
                    'fallback' => $item->fallback,
                    'task_type_id' => $item->task_type_id,
                    'goal_id' => $item->goal_id,
                    'due_date' => $date,
                    'estimate_minutes' => $item->minutes,
                ]);

                $task->fill(['habit' => $item->title]);
                if ($item->fallback && ! $task->fallback) {
                    $task->fallback = $item->fallback;
                }
                $task->save();

                return $user->timeBlocks()->create([
                    'task_id' => $task->id,
                    'title' => $title,
                    'date' => $date,
                    'start_minute' => $item->start_minute,
                    'minutes' => $item->minutes,
                ]);
            });
        });
    }

    /** The slot's alternative done least recently, so a mixed slot rotates instead of repeating. */
    private function pick(ScheduleItem $item, User $user, string $date): string
    {
        $options = $item->alternatives ?? [];
        if ($options === []) {
            return $item->title;
        }

        $last = Task::query()->whereBelongsTo($user)
            ->where('habit', $item->title)
            ->whereIn('title', $options)
            ->where('status', TaskStatus::Completed)
            ->whereDate('due_date', '<', $date)
            ->groupBy('title')
            ->selectRaw('title, max(due_date) as last')
            ->pluck('last', 'title');

        return collect($options)->sortBy(fn (string $option, int $index) => [$last[$option] ?? '', $index])->first();
    }
}
