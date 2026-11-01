<?php

namespace App\Modules\Planner\Providers;

use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Planner\Policies\TimeBlockPolicy;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class PlannerServiceProvider extends ModuleProvider
{
    protected array $policies = [
        TimeBlock::class => TimeBlockPolicy::class,
    ];

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('timeBlocks', fn (User $user) => $user->hasMany(TimeBlock::class));
    }
}
