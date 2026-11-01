<?php

namespace App\Modules\Tasks\Listeners;

use App\Modules\Goals\Models\Goal;
use App\Modules\Tasks\Models\Task;

/**
 * A deleted goal's tasks stay, without a goal.
 */
class DetachTasksFromGoal
{
    public function handle(Goal $goal): void
    {
        Task::query()->where('goal_id', $goal->id)->update(['goal_id' => null]);
    }
}
