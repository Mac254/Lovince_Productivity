<?php

namespace App\Modules\Roadmap\Settings;

use App\Shared\Support\Settings\Setting;
use App\Shared\Support\Settings\SettingsModule;

class RoadmapSettings extends SettingsModule
{
    public function key(): string
    {
        return 'roadmap';
    }

    public function label(): string
    {
        return 'Roadmap';
    }

    public function order(): int
    {
        return 70;
    }

    public function settings(): array
    {
        return [
            Setting::toggle('show_in_sidebar', 'Show in the sidebar', true, 'Hide Roadmap from the sidebar when you’re not using it. The page stays reachable from search.'),
            Setting::toggle('show_done', 'Show done phases', true, 'Keep the Done lane on the roadmap page.'),
        ];
    }
}
