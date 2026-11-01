<?php

namespace App\Modules\Tasks\Actions;

use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;

class ScheduleNextOccurrence
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function __invoke(Task $task): ?Task
    {
        if ($task->recurrence === null || $task->next_occurrence_id !== null || $task->status !== TaskStatus::Completed) {
            return null;
        }

        $today = today();
        $fromDue = $this->settings->resolve($task->user->settings)['tasks']['repeat_from'] === 'due';
        $base = $fromDue && $task->due_date ? $task->due_date : $today;

        $due = $task->recurrence->after($base);
        while ($due->lessThanOrEqualTo($today)) {
            $due = $task->recurrence->after($due);
        }

        $lead = $task->start_date && $task->due_date ? $task->start_date->diffInDays($task->due_date) : null;

        $next = $task->replicate(['status', 'completed_at', 'next_occurrence_id', 'is_highlighted', 'notes_sum_minutes', 'checklist_total', 'checklist_done']);
        $next->fill([
            'status' => TaskStatus::NotStarted,
            'is_highlighted' => false,
            'due_date' => $due->toDateString(),
            'start_date' => $lead === null ? null : $due->copy()->subDays((int) $lead)->toDateString(),
        ]);
        $next->save();

        $task->checklistItems()->get()->each(fn ($item) => $next->checklistItems()->make([
            'title' => $item->title,
            'position' => $item->position,
        ])->user()->associate($task->user_id)->save());

        $task->forceFill(['next_occurrence_id' => $next->id])->saveQuietly();

        return $next;
    }
}
