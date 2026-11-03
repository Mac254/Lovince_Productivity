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
            Setting::choice('accent', 'Accent colour', ['green' => 'Green', 'blue' => 'Blue', 'violet' => 'Violet', 'amber' => 'Amber', 'rose' => 'Rose', 'cyan' => 'Cyan', 'indigo' => 'Indigo', 'graphite' => 'Graphite'], 'green', 'A preset, or any colour from the picker.')->orMatching('/^#[0-9a-f]{6}$/i', '`#${string}`'),
            Setting::choice('corners', 'Corners', ['sharp' => 'Sharp', 'soft' => 'Soft', 'round' => 'Round'], 'round'),
            Setting::choice('font', 'Typeface', [
                'focus' => 'Outfit & Inter',
                'modern' => 'Space Grotesk & DM Sans',
                'studio' => 'Plus Jakarta Sans',
                'inter' => 'Inter',
                'inter_tight' => 'Inter Tight & Inter',
                'geist' => 'Geist',
                'manrope' => 'Manrope',
                'figtree' => 'Figtree',
                'onest' => 'Onest',
                'public_sans' => 'Public Sans',
                'plex' => 'IBM Plex Sans',
                'work_sans' => 'Work Sans',
                'lexend' => 'Lexend',
                'albert' => 'Albert Sans',
                'hanken' => 'Hanken Grotesk',
                'instrument' => 'Instrument Sans',
                'mona' => 'Mona Sans',
                'sora' => 'Sora & Inter',
                'archivo' => 'Archivo',
                'karla' => 'Karla',
                'montserrat' => 'Montserrat & Open Sans',
                'open_sans' => 'Open Sans',
                'roboto' => 'Roboto',
                'atkinson' => 'Atkinson Hyperlegible',
                'editorial' => 'Fraunces & Inter',
                'classic' => 'Source Serif & Source Sans',
                'newsreader' => 'Newsreader & Inter',
                'literata' => 'Literata',
                'lora' => 'Lora & Work Sans',
                'merriweather' => 'Merriweather & Open Sans',
                'playfair' => 'Playfair Display & Inter',
                'garamond' => 'EB Garamond',
                'crimson' => 'Crimson Pro & Karla',
                'bitter' => 'Bitter',
                'roboto_serif' => 'Roboto Serif & Roboto',
                'friendly' => 'Nunito',
                'quicksand' => 'Quicksand',
                'comfortaa' => 'Comfortaa & Nunito',
                'baloo' => 'Baloo 2',
                'fredoka' => 'Fredoka & Nunito',
                'rubik' => 'Rubik',
                'jetbrains' => 'JetBrains Mono',
                'geist_mono' => 'Geist Mono & Geist',
                'fira_code' => 'Fira Code',
                'source_code' => 'Source Code Pro & Source Sans',
                'roboto_mono' => 'Roboto Mono & Roboto',
                'red_hat_mono' => 'Red Hat Mono',
                'grotesk' => 'Space Grotesk & Inter',
                'syne' => 'Syne & Inter',
                'unbounded' => 'Unbounded & Inter',
                'bricolage' => 'Bricolage Grotesque & Inter',
                'system' => 'System',
            ], 'focus', 'Heading and body fonts used across the app.'),
            Setting::choice('text_size', 'Text size', ['small' => 'Small', 'default' => 'Default', 'large' => 'Large', 'larger' => 'Larger'], 'default'),
            Setting::toggle('reduce_motion', 'Reduce motion', false, 'Turns off page and chart animations.'),
        ];
    }
}
