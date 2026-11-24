<?php

namespace App\Modules\Planner\Providers;

use App\Modules\Planner\Console\Commands\SkipMissedRoutines;
use App\Modules\Planner\Models\Schedule;
use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Planner\Policies\SchedulePolicy;
use App\Modules\Planner\Policies\TimeBlockPolicy;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule as Scheduler;

class PlannerServiceProvider extends ModuleProvider
{
    protected array $policies = [
        TimeBlock::class => TimeBlockPolicy::class,
        Schedule::class => SchedulePolicy::class,
    ];

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('timeBlocks', fn (User $user) => $user->hasMany(TimeBlock::class));
        User::resolveRelationUsing('schedules', fn (User $user) => $user->hasMany(Schedule::class));

        $this->commands([SkipMissedRoutines::class]);

        $this->callAfterResolving(Scheduler::class, function (Scheduler $scheduler) {
            // Routine tasks still open once a day has ended are skipped, so they stop piling up as overdue.
            $scheduler->command('planner:skip-missed-routines')->hourly()->withoutOverlapping();
        });
    }
}
