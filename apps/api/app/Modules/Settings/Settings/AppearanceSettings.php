<?php

namespace App\Modules\Settings\Settings;

use App\Shared\Support\Settings\Setting;
use App\Shared\Support\Settings\SettingsModule;

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
            Setting::choice('accent', 'Accent colour', ['green' => 'Green', 'blue' => 'Blue', 'violet' => 'Violet', 'amber' => 'Amber', 'rose' => 'Rose', 'cyan' => 'Cyan', 'indigo' => 'Indigo', 'graphite' => 'Graphite'], 'green'),
            Setting::choice('corners', 'Corners', ['sharp' => 'Sharp', 'soft' => 'Soft', 'round' => 'Round'], 'round'),
            Setting::choice('font', 'Typeface', [
                'focus' => 'Outfit & Inter',
                'editorial' => 'Fraunces & Inter',
                'modern' => 'Space Grotesk & DM Sans',
                'studio' => 'Plus Jakarta Sans',
                'classic' => 'Source Serif & Source Sans',
                'friendly' => 'Nunito',
                'system' => 'System',
            ], 'focus', 'Heading and body fonts used across the app.'),
            Setting::choice('text_size', 'Text size', ['small' => 'Small', 'default' => 'Default', 'large' => 'Large', 'larger' => 'Larger'], 'default'),
            Setting::toggle('reduce_motion', 'Reduce motion', false, 'Turns off page and chart animations.'),
        ];
    }
}
