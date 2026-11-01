<?php

namespace App\Modules\Clock\Providers;

use App\Modules\Clock\Models\Wallpaper;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class ClockServiceProvider extends ModuleProvider
{
    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('wallpapers', fn (User $user) => $user->hasMany(Wallpaper::class));
    }
}
