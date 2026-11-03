<?php

namespace App\Modules\Journal\Settings;

use App\Shared\Support\Settings\Setting;
use App\Shared\Support\Settings\SettingsModule;

class JournalSettings extends SettingsModule
{
    public function key(): string
    {
        return 'journal';
    }

    public function label(): string
    {
        return 'Journal';
    }

    public function order(): int
    {
        return 50;
    }

    public function settings(): array
    {
        return [
            Setting::choice('font', 'Writing font', [
                'mono' => 'JetBrains Mono',
                'sans' => 'App font',
                'serif' => 'Source Serif',
                'literata' => 'Literata',
                'newsreader' => 'Newsreader',
                'lora' => 'Lora',
                'garamond' => 'EB Garamond',
                'merriweather' => 'Merriweather',
                'crimson' => 'Crimson Pro',
                'atkinson' => 'Atkinson Hyperlegible',
                'lexend' => 'Lexend',
                'quicksand' => 'Quicksand',
                'nunito' => 'Nunito',
                'fira_code' => 'Fira Code',
                'geist_mono' => 'Geist Mono',
                'source_code' => 'Source Code Pro',
                'courier' => 'Courier Prime',
                'typewriter' => 'Special Elite',
                'hand' => 'Caveat',
                'patrick_hand' => 'Patrick Hand',
                'kalam' => 'Kalam',
                'indie_flower' => 'Indie Flower',
                'dancing' => 'Dancing Script',
                'architects' => 'Architects Daughter',
            ], 'mono', 'Used in the journal editor and preview.'),
            Setting::toggle('searchable', 'Include in search', true, 'Show journal entries in quick search results.'),
        ];
    }
}
