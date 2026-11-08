<?php

namespace App\Modules\Roadmap\Services;

use App\Modules\Roadmap\Models\RoadmapItem;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Support\Collection;

/**
 * A phase's milestones are its goal's tasks due within the phase's dates, so ticking a task off moves the phase on.
 * Repeating tasks are routines, not checkpoints, so they stay out.
 * Nothing is stored: a task moved to another date simply joins the phase it now falls in.
 */
class PhaseMilestones
{
    /**
     * @param  Collection<int, RoadmapItem>  $items
     * @return Collection<int, RoadmapItem>
     */
    public function attach(Collection $items, User $user): Collection
    {
        $goalIds = $items->pluck('goal_id')->filter()->unique()->values();
        $tasks = $goalIds->isEmpty() ? collect() : Task::query()->whereBelongsTo($user)
            ->whereIn('goal_id', $goalIds)
            ->where('status', '!=', TaskStatus::Cancelled)
            ->whereNull('recurrence')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get(['id', 'goal_id', 'title', 'status', 'due_date', 'recurrence'])
            ->groupBy('goal_id');

        return $items->each(function (RoadmapItem $item) use ($tasks) {
            $from = $item->starts_on?->toDateString();
            $to = $item->ends_on?->toDateString();
            $item->setRelation('milestones', ($tasks[$item->goal_id] ?? collect())
                ->filter(function (Task $task) use ($from, $to) {
                    $due = $task->due_date?->toDateString();
                    if ($from === null && $to === null) {
                        return true;
                    }

                    return $due !== null && ($from === null || $due >= $from) && ($to === null || $due <= $to);
                })
                ->values());
        });
    }
}
