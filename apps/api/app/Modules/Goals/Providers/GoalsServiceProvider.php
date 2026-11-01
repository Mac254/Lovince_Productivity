<?php

namespace App\Modules\Goals\Providers;

use App\Modules\Goals\Models\Goal;
use App\Modules\Goals\Policies\GoalPolicy;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class GoalsServiceProvider extends ModuleProvider
{
    protected array $policies = [Goal::class => GoalPolicy::class];

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('goals', fn (User $user) => $user->hasMany(Goal::class));
    }
}
