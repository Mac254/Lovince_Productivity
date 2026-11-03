<?php

namespace App\Modules\Planner\Actions;

use App\Modules\Planner\Models\Schedule;
use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplySchedule
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * Lays a schedule onto a day: each item becomes a task due that day (reusing one with the same
     * title) and a time block. Existing blocks either make it fail on overlap or are replaced.
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

            return $items->map(function ($item) use ($user, $date) {
                $task = Task::query()->whereBelongsTo($user)->whereDate('due_date', $date)->where('title', $item->title)->first()
                    ?? $user->tasks()->create([
                        'title' => $item->title,
                        'task_type_id' => $item->task_type_id,
                        'goal_id' => $item->goal_id,
                        'due_date' => $date,
                        'estimate_minutes' => $item->minutes,
                    ]);

                return $user->timeBlocks()->create([
                    'task_id' => $task->id,
                    'title' => $item->title,
                    'date' => $date,
                    'start_minute' => $item->start_minute,
                    'minutes' => $item->minutes,
                ]);
            });
        });
    }
}
