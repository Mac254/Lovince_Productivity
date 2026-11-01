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
            Setting::toggle('estimates', 'Time estimates', true, 'Estimate tasks and compare the estimate with the time logged.'),
            Setting::toggle('complete_on_checklist', 'Finish tasks with their checklist', false, 'Mark a task done when you tick the last item on its checklist.'),
            Setting::choice('repeat_from', 'Repeat tasks from', [
                'due' => 'Their due date',
                'completed' => 'The day they’re done',
            ], 'due', 'When you finish a repeating task, the next one is due one interval after this date.'),
        ];
    }
}
