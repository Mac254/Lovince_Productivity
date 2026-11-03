<?php

namespace App\Modules\Reports\Providers;

use App\Modules\Reports\Console\Commands\GenerateDailyReports;
use App\Modules\Reports\Listeners\RefreshReportCompletions;
use App\Modules\Reports\Listeners\RefreshReportWorkLogs;
use App\Modules\Reports\Models\DailyReport;
use App\Modules\Tasks\Events\CompletionsChanged;
use App\Modules\Tasks\Events\WorkLogChanged;
use App\Modules\Users\Models\User;
use App\Shared\Support\ModuleProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

class ReportsServiceProvider extends ModuleProvider
{
    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('dailyReports', fn (User $user) => $user->hasMany(DailyReport::class));

        Event::listen(WorkLogChanged::class, RefreshReportWorkLogs::class);
        Event::listen(CompletionsChanged::class, RefreshReportCompletions::class);

        $this->commands([GenerateDailyReports::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // Snapshot each day just before it ends (APP_TIMEZONE). Run `php artisan schedule:work`
            // locally (included in `pnpm dev`), or a cron entry for `schedule:run` in production.
            $schedule->command('focus:daily-report')->dailyAt('23:55')->withoutOverlapping();

            // Just after midnight, finalise yesterday (and any day missed): the 23:55 run can't see the last minutes.
            $schedule->command('focus:daily-report --missing=7')->dailyAt('00:05')->withoutOverlapping();
        });
    }
}
