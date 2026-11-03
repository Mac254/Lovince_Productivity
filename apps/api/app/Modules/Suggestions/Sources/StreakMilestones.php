<?php

namespace App\Modules\Suggestions\Sources;

use App\Modules\Metrics\Services\ActivityDays;
use App\Modules\Suggestions\Contracts\Suggester;
use App\Modules\Suggestions\Contracts\SuggestionDraft;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

class StreakMilestones implements Suggester
{
    private const MILESTONES = [7, 14, 30, 60, 100, 200, 365];

    public function __construct(private readonly ActivityDays $activity) {}

    public function drafts(User $user, CarbonImmutable $now): iterable
    {
        $today = Carbon::instance($now)->startOfDay();
        $streak = ActivityDays::streaks(
            $this->activity->between($user, $today->copy()->subDays(ActivityDays::LOOKBACK_DAYS), $today),
            $today->toDateString(),
        );
        $milestone = collect(self::MILESTONES)->filter(fn (int $days) => $streak['current'] >= $days)->last();
        if ($milestone === null) {
            return;
        }

        $started = $today->copy()->subDays($streak['current'] - ($streak['active_today'] ? 1 : 0))->toDateString();

        yield new SuggestionDraft(
            key: "streak:{$milestone}:{$started}",
            title: "Mark your {$milestone}-day streak",
            category: SuggestionCategory::Focus,
            body: "You have shown up {$streak['current']} days in a row. Save the milestone, then keep it going.",
            action: ['type' => 'achievement', 'title' => "{$milestone}-day activity streak"],
        );
    }
}
