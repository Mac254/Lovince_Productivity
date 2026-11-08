<?php

namespace App\Modules\Backup\Services;

use App\Modules\Achievements\Models\Achievement;
use App\Modules\Blockers\Models\Blocker;
use App\Modules\Books\Models\Book;
use App\Modules\Books\Models\BookReading;
use App\Modules\Clock\Models\FocusSession;
use App\Modules\Goals\Models\Goal;
use App\Modules\Journal\Models\JournalEntry;
use App\Modules\Planner\Models\Schedule;
use App\Modules\Planner\Models\ScheduleItem;
use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Reviews\Models\WeeklyReview;
use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Suggestions\Enums\SuggestionSource;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Tasks\Models\ChecklistItem;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Tasks\Models\TaskType;
use App\Modules\Users\Models\User;
use BackedEnum;

class BackupExporter
{
    public const FORMAT = 'focus-backup';

    public const VERSION = 2;

    public const VERSIONS = [1, 2];

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
                'cover' => $task->cover,
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
            'schedules' => $user->schedules()->with('items')->orderBy('id')->get()->map(fn (Schedule $schedule) => [
                'ref' => $schedule->id,
                'name' => $schedule->name,
                'days' => $schedule->days,
                'items' => $schedule->items->map(fn (ScheduleItem $item) => [
                    'task_type_ref' => $item->task_type_id,
                    'goal_ref' => $item->goal_id,
                    'title' => $item->title,
                    'start_minute' => $item->start_minute,
                    'minutes' => $item->minutes,
                ])->all(),
            ])->all(),
            'suggestions' => $user->suggestions()
                ->whereNot(fn ($query) => $query->where('source', SuggestionSource::Insight)->where('status', SuggestionStatus::New))
                ->orderBy('id')->get()->map(fn (Suggestion $suggestion) => [
                    'task_ref' => $suggestion->task_id,
                    'source' => $this->value($suggestion->source),
                    'key' => $suggestion->key,
                    'title' => $suggestion->title,
                    'body' => $suggestion->body,
                    'category' => $this->value($suggestion->category),
                    'status' => $this->value($suggestion->status),
                    'action' => $this->action($suggestion->action),
                    'accepted_at' => $suggestion->accepted_at?->toIso8601String(),
                    'done_at' => $suggestion->done_at?->toIso8601String(),
                    'dismissed_at' => $suggestion->dismissed_at?->toIso8601String(),
                ])->all(),
            'achievements' => $user->achievements()->orderBy('achieved_on')->orderBy('id')->get()->map(fn (Achievement $achievement) => [
                'goal_ref' => $achievement->goal_id,
                'task_ref' => $achievement->task_id,
                'title' => $achievement->title,
                'description' => $achievement->description,
                'achieved_on' => $achievement->achieved_on->toDateString(),
                'category' => $this->value($achievement->category),
            ])->all(),
            'books' => $user->books()->with(['readings', 'tasks:id,book_id'])->orderBy('id')->get()->map(fn (Book $book) => [
                'goal_ref' => $book->goal_id,
                'task_refs' => $book->tasks->modelKeys(),
                'title' => $book->title,
                'author' => $book->author,
                'status' => $this->value($book->status),
                'chapters_total' => $book->chapters_total,
                'chapters_read' => $book->chapters_read,
                'started_on' => $book->started_on?->toDateString(),
                'finished_on' => $book->finished_on?->toDateString(),
                'rating' => $book->rating,
                'notes' => $book->notes,
                'readings' => $book->readings->sortBy('read_on')->values()->map(fn (BookReading $reading) => [
                    'read_on' => $reading->read_on->toDateString(),
                    'chapters' => $reading->chapters,
                    'minutes' => $reading->minutes,
                    'note' => $reading->note,
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $action
     * @return array<string, mixed>|null
     */
    private function action(?array $action): ?array
    {
        if ($action === null) {
            return null;
        }

        foreach (['schedule_id' => 'schedule_ref', 'goal_id' => 'goal_ref'] as $id => $ref) {
            if (array_key_exists($id, $action)) {
                $action[$ref] = $action[$id];
                unset($action[$id]);
            }
        }

        return $action;
    }

    private function value(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
