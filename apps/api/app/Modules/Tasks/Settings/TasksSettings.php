<?php

namespace App\Modules\Tasks\Settings;

use App\Shared\Support\Settings\Setting;
use App\Shared\Support\Settings\SettingsModule;

class TasksSettings extends SettingsModule
{
    public function key(): string
    {
        return 'tasks';
    }

    public function label(): string
    {
        return 'Tasks';
    }

    public function order(): int
    {
        return 25;
    }

    public function settings(): array
    {
        return [
            Setting::choice('card_density', 'Task cards', ['comfortable' => 'Comfortable', 'compact' => 'Compact'], 'comfortable', 'Compact cards show more tasks at once.'),
            Setting::toggle('card_covers', 'Cover photos on cards', true),
            Setting::toggle('card_descriptions', 'Descriptions on cards', true, 'The first lines of each task’s description.'),
            Setting::toggle('quick_add', 'Smart quick add', true, 'Read dates, priority, estimates and tags from what you type in quick add.'),
            Setting::toggle('estimates', 'Time estimates', true, 'Estimate tasks and compare the estimate with the time logged.'),
            Setting::toggle('carry_over', 'Move overdue tasks to today', false, 'When you open Today, unfinished tasks from earlier days are due today instead.'),
            Setting::toggle('complete_on_checklist', 'Finish tasks with their checklist', false, 'Mark a task done when you tick the last item on its checklist.'),
            Setting::choice('repeat_from', 'Repeat tasks from', [
                'due' => 'Their due date',
                'completed' => 'The day they’re done',
            ], 'due', 'When you finish a repeating task, the next one is due one interval after this date.'),
            Setting::choice('daily_reminder', 'Daily reminder', ['off' => 'Off'] + collect(range(6, 11))->mapWithKeys(fn (int $hour) => [(string) $hour => sprintf('%02d:00', $hour)])->all(), 'off', 'A notification listing what’s due today, shown while LovinceTracker is open.'),
        ];
    }
}
