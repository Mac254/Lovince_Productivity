<?php

namespace App\Settings\Modules;

use App\Settings\Setting;
use App\Settings\SettingsModule;

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
            Setting::choice('font', 'Writing font', ['mono' => 'Monospace', 'sans' => 'Sans'], 'mono', 'Used in the journal editor.'),
        ];
    }
}
