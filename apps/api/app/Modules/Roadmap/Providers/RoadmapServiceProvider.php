<?php

namespace App\Modules\Roadmap\Providers;

use App\Modules\Roadmap\Models\RoadmapItem;
use App\Modules\Roadmap\Policies\RoadmapItemPolicy;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class RoadmapServiceProvider extends ModuleProvider
{
    protected array $policies = [
        RoadmapItem::class => RoadmapItemPolicy::class,
    ];

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('roadmapItems', fn (User $user) => $user->hasMany(RoadmapItem::class));
    }
}
