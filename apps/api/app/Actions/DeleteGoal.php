<?php

namespace App\Actions;

use App\Models\Goal;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a goal keeps its tasks: they are detached in the same transaction.
 */
class DeleteGoal
{
    public function handle(Goal $goal): void
    {
        DB::transaction(function () use ($goal) {
            $goal->tasks()->update(['goal_id' => null]);
            $goal->delete();
        });
    }
}
