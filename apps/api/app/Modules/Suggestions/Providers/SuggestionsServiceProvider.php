<?php

namespace App\Modules\Suggestions\Providers;

use App\Modules\Suggestions\Console\Commands\FetchIdeas;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Suggestions\Policies\SuggestionPolicy;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule;

class SuggestionsServiceProvider extends ModuleProvider
{
    protected array $policies = [Suggestion::class => SuggestionPolicy::class];

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('suggestions', fn (User $user) => $user->hasMany(Suggestion::class));

        $this->commands([FetchIdeas::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // Only the scheduler reaches the internet; refreshes read whatever the pool already holds.
            $schedule->command('suggestions:fetch-ideas')->everySixHours()->withoutOverlapping();
        });
    }
}
