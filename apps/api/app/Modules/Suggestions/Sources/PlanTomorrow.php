<?php

namespace App\Modules\Suggestions\Sources;

use App\Modules\Planner\Models\Schedule;
use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Suggestions\Contracts\Suggester;
use App\Modules\Suggestions\Contracts\SuggestionDraft;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;

class PlanTomorrow implements Suggester
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function drafts(User $user, CarbonImmutable $now): iterable
    {
        if (! $this->settings->resolve($user->settings)['planner']['plan_ahead']) {
            return;
        }

        $tomorrow = $now->addDay();
        if (TimeBlock::query()->whereBelongsTo($user)->whereDate('date', $tomorrow->toDateString())->exists()) {
            return;
        }

        $schedule = Schedule::query()
            ->whereBelongsTo($user)
            ->orderBy('name')
            ->get()
            ->filter(fn (Schedule $schedule) => empty($schedule->days) || in_array($tomorrow->isoWeekday(), $schedule->days, true))
            ->sortBy(fn (Schedule $schedule) => empty($schedule->days) ? 8 : count($schedule->days))
            ->first();
        if (! $schedule) {
            return;
        }

        yield new SuggestionDraft(
            key: "plan:{$tomorrow->toDateString()}",
            title: "Plan tomorrow with “{$schedule->name}”",
            category: SuggestionCategory::Focus,
            body: 'Tomorrow has nothing planned yet. Apply your routine and wake up to a clear day.',
            action: ['type' => 'apply_schedule', 'schedule_id' => $schedule->id, 'date' => $tomorrow->toDateString()],
        );
    }
}
