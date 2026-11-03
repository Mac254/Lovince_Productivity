<?php

namespace App\Modules\Suggestions\Sources;

use App\Modules\Goals\Enums\GoalStatus;
use App\Modules\Goals\Models\Goal;
use App\Modules\Suggestions\Contracts\Suggester;
use App\Modules\Suggestions\Contracts\SuggestionDraft;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;

class CelebrateGoals implements Suggester
{
    private const WITHIN_DAYS = 14;

    public function drafts(User $user, CarbonImmutable $now): iterable
    {
        $goals = Goal::query()
            ->whereBelongsTo($user)
            ->where('status', GoalStatus::Completed)
            ->where('updated_at', '>=', $now->subDays(self::WITHIN_DAYS))
            ->whereDoesntHave('achievements')
            ->orderBy('id')
            ->get(['id', 'title']);

        foreach ($goals as $goal) {
            yield new SuggestionDraft(
                key: "celebrate:goal:{$goal->id}",
                title: "Celebrate finishing “{$goal->title}”",
                category: SuggestionCategory::Growth,
                body: 'You completed this goal. Save it to your achievements so the win stays on record.',
                action: ['type' => 'achievement', 'goal_id' => $goal->id, 'title' => $goal->title],
            );
        }
    }
}
