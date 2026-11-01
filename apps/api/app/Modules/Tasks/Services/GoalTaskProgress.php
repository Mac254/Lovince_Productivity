<?php

namespace App\Modules\Tasks\Services;

use App\Modules\Goals\Contracts\GoalProgress;
use App\Modules\Tasks\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;

class GoalTaskProgress implements GoalProgress
{
    /**
     * Cancelled tasks are excluded from progress so dropping
     * scope does not drag a goal's percentage down.
     */
    public function apply(Builder $goals): void
    {
        $goals->withCount([
            'tasks as eligible_tasks_count' => fn (Builder $q) => $q->where('status', '!=', TaskStatus::Cancelled),
            'tasks as completed_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Completed),
        ]);
    }
}
