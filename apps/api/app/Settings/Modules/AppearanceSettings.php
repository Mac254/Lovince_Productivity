<?php

namespace App\Settings\Modules;

use App\Settings\Setting;
use App\Settings\SettingsModule;

class AppearanceSettings extends SettingsModule
{
    public function key(): string
    {
        return 'appearance';
    }

    public function label(): string
    {
        return 'Appearance';
    }

    public function order(): int
    {
        return 20;
    }

    public function settings(): array
    {
        return [
            Setting::choice('theme', 'Theme', ['dark' => 'Dark', 'light' => 'Light', 'system' => 'System'], 'dark'),
            Setting::toggle('reduce_motion', 'Reduce motion', false, 'Turns off page and chart animations.'),
        ];
    }
}
