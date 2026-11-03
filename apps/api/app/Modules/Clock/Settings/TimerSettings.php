<?php

namespace App\Modules\Clock\Settings;

use App\Shared\Support\Settings\Setting;
use App\Shared\Support\Settings\SettingsModule;

class TimerSettings extends SettingsModule
{
    public function key(): string
    {
        return 'timer';
    }

    public function label(): string
    {
        return 'Focus timer';
    }

    public function order(): int
    {
        return 40;
    }

    public function settings(): array
    {
        return [
            Setting::toggle('chime', 'Timer chime', true, 'Plays when a focus session or break ends.'),
            Setting::choice('daily_goal', 'Daily focus goal', [
                '0' => 'No goal',
                '60' => '1 hour',
                '120' => '2 hours',
                '180' => '3 hours',
                '240' => '4 hours',
                '300' => '5 hours',
                '360' => '6 hours',
            ], '120', 'Focus time to aim for each day, shown on the clock and dashboard.'),
            Setting::toggle('sync', 'Sync across devices', true, 'Start, pause or stop the timer on one device and see it on the others.'),
            Setting::choice('face', 'Timer face', [
                'ring' => 'Ring',
                'digital' => 'Digital',
                'minimal' => 'Minimal',
                'flip' => 'Flip',
            ], 'ring')->unlisted(),
            Setting::toggle('auto_break', 'Start breaks automatically', false, 'A 5-minute break begins when a focus session ends.'),
        ];
    }
}
