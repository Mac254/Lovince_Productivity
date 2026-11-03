<?php

namespace App\Modules\Planner\Settings;

use App\Shared\Support\Settings\Setting;
use App\Shared\Support\Settings\SettingsModule;

class PlannerSettings extends SettingsModule
{
    public function key(): string
    {
        return 'planner';
    }

    public function label(): string
    {
        return 'Day planner';
    }

    public function order(): int
    {
        return 27;
    }

    public function settings(): array
    {
        $hours = fn (array $values) => collect($values)->mapWithKeys(fn (int $hour) => [(string) $hour => sprintf('%02d:00', $hour)])->all();

        return [
            Setting::toggle('enabled', 'Show the day planner', true, 'Block out time for tasks on a timeline on the Today page.'),
            Setting::choice('day_start', 'Day starts at', $hours(range(5, 10)), '8'),
            Setting::choice('day_end', 'Day ends at', $hours(range(16, 23)), '18'),
            Setting::choice('step', 'Snap blocks to', ['15' => '15 minutes', '30' => '30 minutes', '60' => '1 hour'], '30'),
            Setting::toggle('carry_over', 'Carry unfinished blocks to today', false, 'When you open Today, yesterday’s blocks for unfinished tasks move to the same times today, where they fit.'),
            Setting::choice('reminder', 'Remind me before a block', ['off' => 'Off', '5' => '5 minutes before', '10' => '10 minutes before', '15' => '15 minutes before'], '5', 'Shown while Focus is open.'),
        ];
    }
}
