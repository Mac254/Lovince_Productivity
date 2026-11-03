<?php

namespace App\Modules\Achievements\Providers;

use App\Modules\Achievements\Models\Achievement;
use App\Modules\Achievements\Policies\AchievementPolicy;
use App\Modules\Goals\Models\Goal;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class AchievementsServiceProvider extends ModuleProvider
{
    protected array $policies = [Achievement::class => AchievementPolicy::class];

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('achievements', fn (User $user) => $user->hasMany(Achievement::class));
        Goal::resolveRelationUsing('achievements', fn (Goal $goal) => $goal->hasMany(Achievement::class));
    }
}
