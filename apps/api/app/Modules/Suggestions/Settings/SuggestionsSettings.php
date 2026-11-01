<?php

namespace App\Modules\Suggestions\Settings;

use App\Shared\Support\Settings\Setting;
use App\Shared\Support\Settings\SettingsModule;

class SuggestionsSettings extends SettingsModule
{
    public function key(): string
    {
        return 'suggestions';
    }

    public function label(): string
    {
        return 'Suggestions';
    }

    public function order(): int
    {
        return 28;
    }

    public function settings(): array
    {
        return [
            Setting::toggle('enabled', 'Suggest things to do', true, 'Ideas from your tasks, goals, plans and journal, plus a library of ways to make a day better.'),
            Setting::choice('ideas_per_day', 'Library ideas at a time', ['1' => '1', '2' => '2', '3' => '3', '5' => '5'], '3'),
        ];
    }
}
