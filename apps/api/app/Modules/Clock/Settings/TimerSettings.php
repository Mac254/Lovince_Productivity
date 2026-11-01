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
            Setting::toggle('auto_break', 'Start breaks automatically', false, 'A 5-minute break begins when a focus session ends.'),
        ];
    }
}
