<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DailyReportGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('focus:daily-report {--date= : Day to report on (YYYY-MM-DD), defaults to today}')]
#[Description('Generate and store the daily report for every user')]
class GenerateDailyReports extends Command
{
    public function handle(DailyReportGenerator $generator): int
    {
        $date = $this->option('date') ? Carbon::createFromFormat('!Y-m-d', $this->option('date')) : today();

        if (! $date || $date->isAfter(today())) {
            $this->components->error('Use a valid date that is not in the future.');

            return self::FAILURE;
        }

        $count = 0;
        User::query()->lazyById()->each(function (User $user) use ($generator, $date, &$count) {
            $generator->generate($user, $date);
            $count++;
        });

        $this->components->info("Stored {$count} daily ".str('report')->plural($count)." for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
