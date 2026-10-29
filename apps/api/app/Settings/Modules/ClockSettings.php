<?php

namespace App\Settings\Modules;

use App\Settings\Setting;
use App\Settings\SettingsModule;

class ClockSettings extends SettingsModule
{
    public function key(): string
    {
        return 'clock';
    }

    public function label(): string
    {
        return 'Clock';
    }

    public function description(): string
    {
        return 'The full-screen focus clock.';
    }

    public function order(): int
    {
        return 30;
    }

    public function settings(): array
    {
        return [
            Setting::choice('wallpaper', 'Wallpaper', [
                'neon' => 'Neon glow',
                'grid' => 'Charcoal grid',
                'aurora' => 'Aurora',
                'tones' => 'Job tones',
                'midnight' => 'Midnight',
                'paper' => 'Paper',
            ], 'neon')->orMatching('/^custom:\d+$/', '`custom:${number}`')->unlisted(),
            Setting::choice('time_format', 'Time format', ['24h' => '24-hour', '12h' => '12-hour'], '24h'),
            Setting::toggle('show_seconds', 'Show seconds', true),
            Setting::toggle('ticking', 'Ticking sound', false, 'A soft tick each second while the clock is open.'),
        ];
    }
}
