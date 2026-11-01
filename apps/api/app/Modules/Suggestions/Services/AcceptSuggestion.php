<?php

namespace App\Modules\Suggestions\Services;

use App\Modules\Planner\Actions\ApplySchedule;
use App\Modules\Planner\Models\Schedule;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Tasks\Models\Task;
use Illuminate\Validation\ValidationException;

class AcceptSuggestion
{
    public function __construct(private readonly ApplySchedule $applySchedule) {}

    /**
     * Acts on a suggestion: applies a schedule, or adds a task due on `$date`. Suggestions that only
     * point somewhere (`open`) are just marked accepted.
     */
    public function __invoke(Suggestion $suggestion, string $date): Suggestion
    {
        $action = $suggestion->action ?? ['type' => 'task'];

        match ($action['type'] ?? 'task') {
            'apply_schedule' => ($this->applySchedule)($this->schedule($suggestion, $action), $action['date'] ?? $date),
            'open' => null,
            default => $suggestion->task_id ?? $suggestion->task()->associate($this->task($suggestion, $action, $date)),
        };

        $suggestion->update(['status' => SuggestionStatus::Accepted]);

        return $suggestion;
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function schedule(Suggestion $suggestion, array $action): Schedule
    {
        return Schedule::query()->whereBelongsTo($suggestion->user)->find($action['schedule_id'] ?? 0)
            ?? throw ValidationException::withMessages(['action' => 'That schedule no longer exists.']);
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function task(Suggestion $suggestion, array $action, string $date): Task
    {
        return $suggestion->user->tasks()->create([
            'title' => $suggestion->title,
            'description' => $suggestion->body,
            'goal_id' => $action['goal_id'] ?? null,
            'estimate_minutes' => $action['minutes'] ?? null,
            'due_date' => $date,
        ]);
    }
}
