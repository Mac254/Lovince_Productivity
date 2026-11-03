<?php

namespace App\Modules\Suggestions\Sources;

use App\Modules\Reviews\Models\WeeklyReview;
use App\Modules\Suggestions\Contracts\Suggester;
use App\Modules\Suggestions\Contracts\SuggestionDraft;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;

class UnreviewedWeek implements Suggester
{
    private const UNTIL_WEEKDAY = 3;

    public function drafts(User $user, CarbonImmutable $now): iterable
    {
        if ($now->isoWeekday() > self::UNTIL_WEEKDAY) {
            return;
        }

        $week = $now->startOfWeek()->subWeek();
        $reviewed = WeeklyReview::query()->whereBelongsTo($user)
            ->whereDate('week_start', $week->toDateString())
            ->whereNotNull('completed_at')
            ->exists();
        $worked = Task::query()->whereBelongsTo($user)
            ->where('status', TaskStatus::Completed)
            ->whereBetween('completed_at', [$week, $week->endOfWeek()])
            ->exists();
        if ($reviewed || ! $worked) {
            return;
        }

        yield new SuggestionDraft(
            key: "review:{$week->toDateString()}",
            title: 'Review last week',
            category: SuggestionCategory::Growth,
            body: 'Look back at what you finished and choose next week’s focus. It takes about ten minutes.',
            action: ['type' => 'open', 'route' => 'review', 'params' => ['week' => $week->toDateString()]],
        );
    }
}
