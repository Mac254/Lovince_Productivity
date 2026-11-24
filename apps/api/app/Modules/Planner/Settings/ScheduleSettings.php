<?php

namespace App\Modules\Planner\Settings;

use App\Shared\Support\Settings\Setting;
use App\Shared\Support\Settings\SettingsModule;

class ScheduleSettings extends SettingsModule
{
    public function key(): string
    {
        return 'schedules';
    }

    public function label(): string
    {
        return 'Schedules';
    }

    public function order(): int
    {
        return 28;
    }

    public function settings(): array
    {
        return [
            Setting::toggle('skip_missed', 'Skip routine tasks left open', true, 'Once a day is over, its routine tasks still open are skipped, so they don’t pile up as overdue.'),
            Setting::toggle('show_balance', 'Show each routine’s balance', true, 'A bar on each schedule showing how its time splits across your task types.'),
            Setting::choice('activity_minutes', 'New activities last', ['15' => '15 minutes', '30' => '30 minutes', '45' => '45 minutes', '60' => '1 hour'], '30', 'The length a new activity starts with when you add it to a schedule.'),
        ];
    }
}
