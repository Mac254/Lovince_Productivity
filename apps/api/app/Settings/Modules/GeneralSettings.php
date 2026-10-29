<?php

namespace App\Settings\Modules;

use App\Settings\Setting;
use App\Settings\SettingsModule;

class GeneralSettings extends SettingsModule
{
    public function key(): string
    {
        return 'general';
    }

    public function label(): string
    {
        return 'General';
    }

    public function order(): int
    {
        return 10;
    }

    public function settings(): array
    {
        return [
            Setting::choice('start_page', 'Open Focus on', [
                'dashboard' => 'Dashboard',
                'today' => 'Today',
                'focus' => 'Focus clock',
            ], 'dashboard', 'Where you land when you open Focus or sign in.'),
            Setting::toggle('show_quotes', 'Daily quote', true, 'On the dashboard and the focus clock.'),
        ];
    }
}
