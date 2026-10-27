<?php

namespace App\Support;

use App\Enums\BlockerStatus;
use App\Enums\GoalStatus;
use App\Enums\GoalTimeframe;
use App\Enums\GoalTone;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\Task\IndexTaskRequest;

/**
 * The single source for values shared with the TypeScript apps.
 * `php artisan focus:types` writes it to packages/types.
 */
final class Contract
{
    public const TASK_TITLE_MAX = 200;

    public const GOAL_TITLE_MAX = 160;

    public const DESCRIPTION_MAX = 5000;

    public const SEARCH_MAX = 100;

    public const PER_PAGE_MAX = 100;

    public const PER_PAGE_DEFAULT = 25;

    public const JOURNAL_MAX = 20000;

    public const BLOCKER_MAX = 1000;

    public const METRIC_RANGES = [7, 30, 90];

    public const AVATAR_MAX_KB = 2048;

    public const TASK_NOTE_MAX = 5000;

    /** Twelve hours: the most one log entry can claim. */
    public const TASK_NOTE_MINUTES_MAX = 720;

    public const TASK_TYPE_NAME_MAX = 40;

    public const TASK_TYPE_LIMIT = 30;

    public const WALLPAPER_MAX_KB = 8192;

    public const WALLPAPER_LIMIT = 12;

    /**
     * @return array<string, list<string>>
     */
    public static function enums(): array
    {
        $values = fn (array $cases) => array_map(fn ($case) => $case->value, $cases);

        return [
            'TaskStatus' => $values(TaskStatus::cases()),
            'OpenTaskStatus' => TaskStatus::openValues(),
            'TaskPriority' => $values(TaskPriority::cases()),
            'TaskCategory' => $values(TaskCategory::cases()),
            'GoalStatus' => $values(GoalStatus::cases()),
            'GoalTimeframe' => $values(GoalTimeframe::cases()),
            'GoalTone' => $values(GoalTone::cases()),
            'TaskDueFilter' => IndexTaskRequest::DUE_FILTERS,
            'TaskSort' => IndexTaskRequest::SORTS,
            'BlockerStatus' => $values(BlockerStatus::cases()),
        ];
    }

    /**
     * @return array<string, int|array<string, int>>
     */
    public static function limits(): array
    {
        return [
            'task' => ['title' => self::TASK_TITLE_MAX, 'description' => self::DESCRIPTION_MAX],
            'goal' => ['title' => self::GOAL_TITLE_MAX, 'description' => self::DESCRIPTION_MAX],
            'search' => self::SEARCH_MAX,
            'perPage' => self::PER_PAGE_MAX,
            'perPageDefault' => self::PER_PAGE_DEFAULT,
            'journal' => self::JOURNAL_MAX,
            'blocker' => self::BLOCKER_MAX,
            'taskNote' => ['body' => self::TASK_NOTE_MAX, 'minutes' => self::TASK_NOTE_MINUTES_MAX],
            'taskType' => ['name' => self::TASK_TYPE_NAME_MAX, 'max' => self::TASK_TYPE_LIMIT],
            'avatarKb' => self::AVATAR_MAX_KB,
            'wallpaperKb' => self::WALLPAPER_MAX_KB,
            'wallpapers' => self::WALLPAPER_LIMIT,
        ];
    }
}
