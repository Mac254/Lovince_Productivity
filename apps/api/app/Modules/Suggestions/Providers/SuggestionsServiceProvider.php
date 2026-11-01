<?php

namespace App\Modules\Suggestions\Providers;

use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Suggestions\Policies\SuggestionPolicy;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;

class SuggestionsServiceProvider extends ModuleProvider
{
    protected array $policies = [Suggestion::class => SuggestionPolicy::class];

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('suggestions', fn (User $user) => $user->hasMany(Suggestion::class));
    }
}
