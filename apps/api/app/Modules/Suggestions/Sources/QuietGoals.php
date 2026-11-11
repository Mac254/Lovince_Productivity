<?php

namespace App\Modules\Suggestions\Sources;

use App\Modules\Goals\Enums\GoalStatus;
use App\Modules\Goals\Models\Goal;
use App\Modules\Suggestions\Contracts\Suggester;
use App\Modules\Suggestions\Contracts\SuggestionDraft;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;

class QuietGoals implements Suggester
{
    public function drafts(User $user, CarbonImmutable $now): iterable
    {
        $goals = Goal::query()
            ->whereBelongsTo($user)
            ->where('status', GoalStatus::Active)
            ->where('created_at', '<', $now->subDays(7))
            ->whereDoesntHave('tasks', fn ($q) => $q->whereBelongsTo($user)->where('completed_at', '>=', $now->subDays(7)))
            ->orderBy('id')
            ->get(['id', 'title']);
        $week = $now->format('o-\WW');

        foreach ($goals as $goal) {
            yield new SuggestionDraft(
                key: "goal:{$goal->id}:{$week}",
                title: "Give “{$goal->title}” 30 minutes today",
                category: SuggestionCategory::Growth,
                body: 'Nothing on this goal was finished this week. One small step keeps it alive.',
                action: ['type' => 'task', 'goal_id' => $goal->id, 'minutes' => 30],
            );
        }
    }
}
