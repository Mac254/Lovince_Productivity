<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDailyReport;
use App\Models\DailyReport;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('focus:daily-report
    {--date= : Day to report on (YYYY-MM-DD), defaults to today}
    {--missing= : Instead, fill gaps: queue reports for any of the last N days (before today) that have none}')]
#[Description('Queue the daily report for every user (the queue worker stores them)')]
class GenerateDailyReports extends Command
{
    public function handle(): int
    {
        if ($this->option('missing') !== null) {
            return $this->fillGaps((int) $this->option('missing'));
        }

        $date = $this->option('date') ? Carbon::createFromFormat('!Y-m-d', $this->option('date')) : today();

        if (! $date || $date->isAfter(today())) {
            $this->components->error('Use a valid date that is not in the future.');

            return self::FAILURE;
        }

        $count = 0;
        User::query()->lazyById()->each(function (User $user) use ($date, &$count) {
            GenerateDailyReport::dispatch($user, $date->toDateString());
            $count++;
        });

        $this->components->info("Queued {$count} daily ".str('report')->plural($count)." for {$date->toDateString()}.");

        return self::SUCCESS;
    }

    /**
     * The 23:55 run only happens while the app is up, so days it was closed get their report
     * when it next starts. Counts come from timestamps, so they are exact for past days.
     */
    private function fillGaps(int $days): int
    {
        if ($days < 1 || $days > 31) {
            $this->components->error('Use --missing with 1 to 31 days.');

            return self::FAILURE;
        }

        $count = 0;
        User::query()->lazyById()->each(function (User $user) use ($days, &$count) {
            $have = DailyReport::query()->whereBelongsTo($user)
                ->whereDate('date', '>=', today()->subDays($days))
                ->pluck('date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->all();

            foreach (range($days, 1) as $back) {
                $day = today()->subDays($back);
                if ($day->endOfDay()->isBefore($user->created_at) || in_array($day->toDateString(), $have, true)) {
                    continue;
                }
                GenerateDailyReport::dispatch($user, $day->toDateString());
                $count++;
            }
        });

        $this->components->info($count ? "Queued {$count} missing daily ".str('report')->plural($count).'.' : 'No missing reports.');

        return self::SUCCESS;
    }
}
