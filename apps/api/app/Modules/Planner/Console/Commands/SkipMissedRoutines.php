<?php

namespace App\Modules\Planner\Console\Commands;

use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use App\Modules\Users\Services\AccountTimezone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('planner:skip-missed-routines')]
#[Description('Skip routine tasks left open on a past day, so they stop showing as overdue')]
class SkipMissedRoutines extends Command
{
    public function handle(SettingsRegistry $settings): int
    {
        $count = 0;
        User::query()->whereHas('schedules')->lazyById()->each(function (User $user) use ($settings, &$count) {
            if (! $settings->resolve($user->settings)['schedules']['skip_missed']) {
                return;
            }
            $count += AccountTimezone::run($user, fn () => $this->skip($user));
        });

        $this->components->info("Skipped {$count} missed routine ".str('task')->plural($count).'.');

        return self::SUCCESS;
    }

    private function skip(User $user): int
    {
        $skipped = 0;
        Task::query()
            ->whereBelongsTo($user)
            ->whereNotNull('habit')
            ->whereIn('status', [TaskStatus::NotStarted, TaskStatus::InProgress])
            ->whereDate('due_date', '<', today()->toDateString())
            ->lazyById()
            ->each(function (Task $task) use (&$skipped) {
                $task->update(['status' => TaskStatus::Cancelled, 'cancel_reason' => 'Not done on the day']);
                $skipped++;
            });

        return $skipped;
    }
}
