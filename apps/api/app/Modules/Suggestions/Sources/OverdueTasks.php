<?php

namespace App\Modules\Suggestions\Sources;

use App\Modules\Suggestions\Contracts\Suggester;
use App\Modules\Suggestions\Contracts\SuggestionDraft;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;

class OverdueTasks implements Suggester
{
    public function drafts(User $user, CarbonImmutable $now): iterable
    {
        $count = Task::query()->whereBelongsTo($user)->open()->whereDate('due_date', '<', $now->toDateString())->count();
        if ($count === 0) {
            return;
        }

        yield new SuggestionDraft(
            key: "overdue:{$now->toDateString()}",
            title: $count === 1 ? 'Reschedule your overdue task' : "Reschedule {$count} overdue tasks",
            category: SuggestionCategory::Focus,
            body: 'Give each one a new date or let it go, so today’s list only holds what you will really do.',
            action: ['type' => 'open', 'route' => 'tasks'],
        );
    }
}
