<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDailyReport;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('focus:daily-report {--date= : Day to report on (YYYY-MM-DD), defaults to today}')]
#[Description('Queue the daily report for every user (the queue worker stores them)')]
class GenerateDailyReports extends Command
{
    public function handle(): int
    {
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
}
