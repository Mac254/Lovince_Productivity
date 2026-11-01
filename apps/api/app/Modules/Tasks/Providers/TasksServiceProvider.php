<?php

namespace App\Modules\Tasks\Providers;

use App\Modules\Goals\Contracts\GoalProgress;
use App\Modules\Goals\Models\Goal;
use App\Modules\Tasks\Listeners\DetachTasksFromGoal;
use App\Modules\Tasks\Models\ChecklistItem;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Tasks\Models\TaskType;
use App\Modules\Tasks\Policies\ChecklistItemPolicy;
use App\Modules\Tasks\Policies\TaskNotePolicy;
use App\Modules\Tasks\Policies\TaskPolicy;
use App\Modules\Tasks\Policies\TaskTypePolicy;
use App\Modules\Tasks\Services\GoalTaskProgress;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class TasksServiceProvider extends ModuleProvider
{
    protected array $policies = [
        Task::class => TaskPolicy::class,
        TaskNote::class => TaskNotePolicy::class,
        TaskType::class => TaskTypePolicy::class,
        ChecklistItem::class => ChecklistItemPolicy::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(GoalProgress::class, GoalTaskProgress::class);
    }

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('tasks', fn (User $user) => $user->hasMany(Task::class));
        User::resolveRelationUsing('taskTypes', fn (User $user) => $user->hasMany(TaskType::class));

        // A goal's progress is counted from its tasks, and deleting it keeps them.
        Goal::resolveRelationUsing('tasks', fn (Goal $goal) => $goal->hasMany(Task::class));
        Goal::deleting(DetachTasksFromGoal::class);
    }
}
