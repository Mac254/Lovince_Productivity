<?php

namespace App\Modules\Clock\Services;

use App\Modules\Clock\Models\FocusSession;
use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FocusStats
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function goalMinutes(User $user): int
    {
        return (int) ($this->settings->resolve($user->settings)['timer']['daily_goal'] ?? 0);
    }

    /**
     * @return Collection<int, FocusSession>
     */
    public function between(User $user, Carbon $from, Carbon $to): Collection
    {
        return FocusSession::query()
            ->whereBelongsTo($user)
            ->whereDate('started_on', '>=', $from->toDateString())
            ->whereDate('started_on', '<=', $to->toDateString())
            ->get();
    }

    /**
     * @param  Collection<int, FocusSession>  $sessions
     */
    public static function minutes(Collection $sessions): int
    {
        return (int) round($sessions->sum('focused_seconds') / 60);
    }

    /**
     * @param  Collection<int, FocusSession>  $sessions
     * @return array<string, int>
     */
    public static function minutesByDay(Collection $sessions): array
    {
        return $sessions
            ->groupBy(fn (FocusSession $session) => $session->started_on->toDateString())
            ->map(fn (Collection $day) => self::minutes($day))
            ->all();
    }

    /**
     * @return array{minutes: int, sessions: int, goal_minutes: int}
     */
    public function today(User $user): array
    {
        $sessions = $this->between($user, today(), today());

        return [
            'minutes' => self::minutes($sessions),
            'sessions' => $sessions->count(),
            'goal_minutes' => $this->goalMinutes($user),
        ];
    }
}
