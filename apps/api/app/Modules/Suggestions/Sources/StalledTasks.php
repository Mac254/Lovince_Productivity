<?php

namespace App\Modules\Suggestions\Sources;

use App\Modules\Suggestions\Contracts\Suggester;
use App\Modules\Suggestions\Contracts\SuggestionDraft;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;

class StalledTasks implements Suggester
{
    private const QUIET_DAYS = 7;

    private const LIMIT = 2;

    public function drafts(User $user, CarbonImmutable $now): iterable
    {
        $since = $now->subDays(self::QUIET_DAYS);
        $tasks = Task::query()
            ->whereBelongsTo($user)
            ->where('status', TaskStatus::InProgress)
            ->where('updated_at', '<', $since)
            ->whereDoesntHave('notes', fn ($q) => $q->whereDate('logged_on', '>=', $since->toDateString()))
            ->orderBy('updated_at')
            ->limit(self::LIMIT)
            ->get(['id', 'title', 'estimate_minutes']);

        foreach ($tasks as $task) {
            yield new SuggestionDraft(
                key: "stalled:{$task->id}:{$now->format('o-\WW')}",
                title: "Pick “{$task->title}” back up",
                category: SuggestionCategory::Focus,
                body: 'It has been in progress for over a week with nothing logged. Give it one focused session, or let it go.',
                action: ['type' => 'open', 'route' => 'task-detail', 'params' => ['id' => $task->id]],
            );
        }
    }
}
