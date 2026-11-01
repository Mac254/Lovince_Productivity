<?php

namespace App\Modules\Clock\Providers;

use App\Modules\Clock\Models\FocusSession;
use App\Modules\Clock\Models\Wallpaper;
use App\Modules\Clock\Policies\FocusSessionPolicy;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class ClockServiceProvider extends ModuleProvider
{
    protected array $policies = [
        FocusSession::class => FocusSessionPolicy::class,
    ];

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('wallpapers', fn (User $user) => $user->hasMany(Wallpaper::class));
        User::resolveRelationUsing('focusSessions', fn (User $user) => $user->hasMany(FocusSession::class));
    }
}
