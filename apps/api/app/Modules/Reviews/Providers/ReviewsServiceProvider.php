<?php

namespace App\Modules\Reviews\Providers;

use App\Modules\Reviews\Models\WeeklyReview;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class ReviewsServiceProvider extends ModuleProvider
{
    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('weeklyReviews', fn (User $user) => $user->hasMany(WeeklyReview::class));
    }
}
