<?php

namespace App\Modules\Blockers\Providers;

use App\Modules\Blockers\Models\Blocker;
use App\Modules\Blockers\Policies\BlockerPolicy;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class BlockersServiceProvider extends ModuleProvider
{
    protected array $policies = [Blocker::class => BlockerPolicy::class];

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('blockers', fn (User $user) => $user->hasMany(Blocker::class));
        Task::resolveRelationUsing('blockers', fn (Task $task) => $task->hasMany(Blocker::class));
    }
}
