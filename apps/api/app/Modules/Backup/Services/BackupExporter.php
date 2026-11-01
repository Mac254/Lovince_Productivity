<?php

namespace App\Modules\Backup\Services;

use App\Modules\Blockers\Models\Blocker;
use App\Modules\Clock\Models\FocusSession;
use App\Modules\Goals\Models\Goal;
use App\Modules\Journal\Models\JournalEntry;
use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Reviews\Models\WeeklyReview;
use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Tasks\Models\ChecklistItem;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Tasks\Models\TaskType;
use App\Modules\Users\Models\User;
use BackedEnum;

class BackupExporter
{
    public const FORMAT = 'focus-backup';

    public const VERSION = 1;

    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'settings' => $this->settings->resolve($user->settings),
            'task_types' => $user->taskTypes()->orderBy('id')->get()->map(fn (TaskType $type) => [
                'ref' => $type->id,
                'name' => $type->name,
                'tone' => $this->value($type->tone),
            ])->all(),
            'goals' => $user->goals()->orderBy('id')->get()->map(fn (Goal $goal) => [
                'ref' => $goal->id,
                'title' => $goal->title,
                'description' => $goal->description,
                'status' => $this->value($goal->status),
                'timeframe' => $this->value($goal->timeframe),
                'tone' => $this->value($goal->tone),
                'start_date' => $goal->start_date?->toDateString(),
                'target_date' => $goal->target_date?->toDateString(),
            ])->all(),
            'tasks' => $user->tasks()->with(['notes', 'checklistItems'])->orderBy('id')->get()->map(fn (Task $task) => [
                'ref' => $task->id,
                'goal_ref' => $task->goal_id,
                'task_type_ref' => $task->task_type_id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $this->value($task->status),
                'priority' => $this->value($task->priority),
                'category' => $this->value($task->category),
                'recurrence' => $this->value($task->recurrence),
                'is_highlighted' => (bool) $task->is_highlighted,
                'estimate_minutes' => $task->estimate_minutes,
                'start_date' => $task->start_date?->toDateString(),
                'due_date' => $task->due_date?->toDateString(),
                'completed_at' => $task->completed_at?->toIso8601String(),
                'created_at' => $task->created_at?->toIso8601String(),
                'notes' => $task->notes->map(fn (TaskNote $note) => [
                    'logged_on' => $note->logged_on->toDateString(),
                    'body' => $note->body,
                    'minutes' => $note->minutes,
                ])->all(),
                'checklist' => $task->checklistItems->map(fn (ChecklistItem $item) => [
                    'title' => $item->title,
                    'done' => (bool) $item->done,
                ])->all(),
            ])->all(),
            'blockers' => $user->blockers()->orderBy('id')->get()->map(fn (Blocker $blocker) => [
                'task_ref' => $blocker->task_id,
                'description' => $blocker->description,
                'status' => $this->value($blocker->status),
                'resolved_at' => $blocker->resolved_at?->toIso8601String(),
            ])->all(),
            'journal' => $user->journalEntries()->orderBy('date')->get()->map(fn (JournalEntry $entry) => [
                'date' => $entry->date->toDateString(),
                'body' => $entry->body,
            ])->all(),
            'focus_sessions' => $user->focusSessions()->orderBy('started_at')->get()->map(fn (FocusSession $session) => [
                'task_ref' => $session->task_id,
                'started_on' => $session->started_on->toDateString(),
                'started_at' => $session->started_at->toIso8601String(),
                'ended_at' => $session->ended_at->toIso8601String(),
                'planned_minutes' => $session->planned_minutes,
                'focused_seconds' => $session->focused_seconds,
                'completed' => $session->completed,
            ])->all(),
            'weekly_reviews' => $user->weeklyReviews()->orderBy('week_start')->get()->map(fn (WeeklyReview $review) => [
                'week_start' => $review->week_start->toDateString(),
                'wins' => $review->wins,
                'challenges' => $review->challenges,
                'next_focus' => $review->next_focus,
                'completed_at' => $review->completed_at?->toIso8601String(),
            ])->all(),
            'time_blocks' => $user->timeBlocks()->orderBy('date')->orderBy('start_minute')->get()->map(fn (TimeBlock $block) => [
                'task_ref' => $block->task_id,
                'title' => $block->title,
                'date' => $block->date->toDateString(),
                'start_minute' => $block->start_minute,
                'minutes' => $block->minutes,
            ])->all(),
        ];
    }

    private function value(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
