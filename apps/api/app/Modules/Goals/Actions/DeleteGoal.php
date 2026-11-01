<?php

namespace App\Modules\Goals\Actions;

use App\Modules\Goals\Models\Goal;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a goal keeps its tasks: the Tasks module detaches them as the goal is deleted
 * (DetachTasksFromGoal), inside this transaction.
 */
class DeleteGoal
{
    public function handle(Goal $goal): void
    {
        DB::transaction(fn () => $goal->delete());
    }
}
