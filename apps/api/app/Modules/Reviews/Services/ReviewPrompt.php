<?php

namespace App\Modules\Reviews\Services;

use App\Modules\Reviews\Enums\ReviewPeriod;
use App\Modules\Reviews\Models\Review;
use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Users\Models\User;

class ReviewPrompt
{
    private const DAYS = ['friday' => 5, 'saturday' => 6, 'sunday' => 7];

    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @return array{week_start: string, due: bool, done: bool}
     */
    public function for(User $user): array
    {
        $today = today();
        $weekStart = $today->copy()->startOfWeek();
        $day = self::DAYS[$this->settings->resolve($user->settings)['review']['day']] ?? null;
        $done = Review::query()->whereBelongsTo($user)->for(ReviewPeriod::Week, $weekStart)->whereNotNull('completed_at')->exists();

        return [
            'week_start' => $weekStart->toDateString(),
            'due' => $day !== null && $today->dayOfWeekIso >= $day,
            'done' => $done,
        ];
    }
}
