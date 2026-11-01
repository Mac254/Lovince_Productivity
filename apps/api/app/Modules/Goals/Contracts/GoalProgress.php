<?php

namespace App\Modules\Goals\Contracts;

use App\Modules\Goals\Models\Goal;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adds `eligible_tasks_count` and `completed_tasks_count` to a goal query, which `Goal::progress()`
 * reads. Implemented by the Tasks module, which owns tasks and their statuses.
 */
interface GoalProgress
{
    /**
     * @param  Builder<Goal>  $goals
     */
    public function apply(Builder $goals): void;
}
