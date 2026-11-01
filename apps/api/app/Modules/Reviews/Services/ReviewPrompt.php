<?php

namespace App\Modules\Reviews\Services;

use App\Modules\Reviews\Models\WeeklyReview;
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
        $done = WeeklyReview::query()->whereBelongsTo($user)->whereDate('week_start', $weekStart->toDateString())->whereNotNull('completed_at')->exists();

        return [
            'week_start' => $weekStart->toDateString(),
            'due' => $day !== null && $today->dayOfWeekIso >= $day,
            'done' => $done,
        ];
    }
}
