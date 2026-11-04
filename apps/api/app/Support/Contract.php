<?php

namespace App\Support;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\CategoryKind;
use App\Modules\Accounting\Enums\Frequency;
use App\Modules\Accounting\Enums\TransactionDirection;
use App\Modules\Accounting\Enums\TransactionStatus;
use App\Modules\Accounting\Enums\TransactionType;
use App\Modules\Accounting\Models\RecurringTransaction;
use App\Modules\Blockers\Enums\BlockerStatus;
use App\Modules\Goals\Enums\GoalStatus;
use App\Modules\Goals\Enums\GoalTimeframe;
use App\Modules\Goals\Enums\GoalTone;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Suggestions\Enums\SuggestionSource;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Tasks\Enums\TaskCategory;
use App\Modules\Tasks\Enums\TaskPriority;
use App\Modules\Tasks\Enums\TaskRecurrence;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Http\Requests\IndexTaskRequest;

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

    public const BACKUP_MAX_KB = 20480;

    public const BACKUP_RECORDS_MAX = 50000;

    public const PER_PAGE_MAX = 100;

    public const PER_PAGE_DEFAULT = 25;

    public const JOURNAL_MAX = 20000;

    public const BLOCKER_MAX = 1000;

    public const METRIC_RANGES = [7, 30, 90];

    public const AVATAR_MAX_KB = 2048;

    public const TASK_NOTE_MAX = 5000;

    /** Twelve hours: the most one log entry can claim. */
    public const TASK_NOTE_MINUTES_MAX = 720;

    public const TASK_ESTIMATE_MINUTES_MAX = 6000;

    public const TASK_TYPE_NAME_MAX = 40;

    public const TASK_TYPE_LIMIT = 30;

    public const WALLPAPER_MAX_KB = 8192;

    public const COVER_MAX_KB = 8192;

    public const COVERS = ['focus-1', 'focus-2', 'focus-3', 'focus-4', 'growth-1', 'growth-2', 'growth-3', 'growth-4', 'health-1', 'health-2', 'health-3', 'health-4', 'wellbeing-1', 'wellbeing-2', 'wellbeing-3', 'wellbeing-4', 'social-1', 'social-2', 'social-3', 'social-4', 'fun-1', 'fun-2', 'fun-3', 'fun-4'];

    public const WALLPAPER_LIMIT = 12;

    public const FOCUS_SESSION_MINUTES_MAX = 720;

    public const REVIEW_MAX = 5000;

    public const CHECKLIST_ITEM_MAX = 200;

    public const CHECKLIST_LIMIT = 50;

    public const TIME_BLOCK_TITLE_MAX = 120;

    public const TIME_BLOCK_MINUTES_MIN = 5;

    public const TIME_BLOCK_MINUTES_MAX = 720;

    public const SCHEDULE_NAME_MAX = 80;

    public const SCHEDULE_ITEM_LIMIT = 40;

    public const SUGGESTION_TITLE_MAX = 160;

    public const SUGGESTION_BODY_MAX = 1000;

    public const ACHIEVEMENT_TITLE_MAX = 160;

    public const ACCOUNTING_ACCOUNT_NAME_MAX = 80;

    public const ACCOUNTING_CATEGORY_NAME_MAX = 60;

    public const ACCOUNTING_DESCRIPTION_MAX = 160;

    public const ACCOUNTING_NOTES_MAX = 1000;

    public const ACCOUNTING_REFERENCE_MAX = 80;

    /** The largest single amount in minor units: just under 10 trillion in a two-decimal currency. */
    public const ACCOUNTING_AMOUNT_MAX = 999_999_999_999_999;

    /** The currencies accounts can hold, with their minor-unit decimal places. */
    public const CURRENCY_DECIMALS = ['KES' => 2, 'UGX' => 0, 'TZS' => 2, 'RWF' => 0, 'ETB' => 2, 'NGN' => 2, 'ZAR' => 2, 'USD' => 2, 'EUR' => 2, 'GBP' => 2];

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
            'TaskRecurrence' => $values(TaskRecurrence::cases()),
            'GoalStatus' => $values(GoalStatus::cases()),
            'GoalTimeframe' => $values(GoalTimeframe::cases()),
            'GoalTone' => $values(GoalTone::cases()),
            'TaskDueFilter' => IndexTaskRequest::DUE_FILTERS,
            'TaskSort' => IndexTaskRequest::SORTS,
            'Cover' => self::COVERS,
            'BlockerStatus' => $values(BlockerStatus::cases()),
            'SuggestionCategory' => $values(SuggestionCategory::cases()),
            'SuggestionStatus' => $values(SuggestionStatus::cases()),
            'SuggestionSource' => $values(SuggestionSource::cases()),
            'AchievementCategory' => $values(SuggestionCategory::cases()),
            'FinancialAccountType' => $values(AccountType::cases()),
            'TransactionType' => $values(TransactionType::cases()),
            'TransactionStatus' => $values(TransactionStatus::cases()),
            'TransactionDirection' => $values(TransactionDirection::cases()),
            'CategoryKind' => $values(CategoryKind::cases()),
            'Frequency' => $values(Frequency::cases()),
            'RecurringTransactionType' => $values(RecurringTransaction::TYPES),
            'Currency' => array_keys(self::CURRENCY_DECIMALS),
        ];
    }

    /**
     * @return array<string, int|array<string, int>>
     */
    public static function limits(): array
    {
        return [
            'task' => ['title' => self::TASK_TITLE_MAX, 'description' => self::DESCRIPTION_MAX, 'estimateMinutes' => self::TASK_ESTIMATE_MINUTES_MAX],
            'goal' => ['title' => self::GOAL_TITLE_MAX, 'description' => self::DESCRIPTION_MAX],
            'search' => self::SEARCH_MAX,
            'backupKb' => self::BACKUP_MAX_KB,
            'perPage' => self::PER_PAGE_MAX,
            'perPageDefault' => self::PER_PAGE_DEFAULT,
            'journal' => self::JOURNAL_MAX,
            'blocker' => self::BLOCKER_MAX,
            'taskNote' => ['body' => self::TASK_NOTE_MAX, 'minutes' => self::TASK_NOTE_MINUTES_MAX],
            'taskType' => ['name' => self::TASK_TYPE_NAME_MAX, 'max' => self::TASK_TYPE_LIMIT],
            'avatarKb' => self::AVATAR_MAX_KB,
            'wallpaperKb' => self::WALLPAPER_MAX_KB,
            'wallpapers' => self::WALLPAPER_LIMIT,
            'coverKb' => self::COVER_MAX_KB,
            'focusSessionMinutes' => self::FOCUS_SESSION_MINUTES_MAX,
            'review' => self::REVIEW_MAX,
            'checklist' => ['title' => self::CHECKLIST_ITEM_MAX, 'max' => self::CHECKLIST_LIMIT],
            'timeBlock' => ['title' => self::TIME_BLOCK_TITLE_MAX, 'minutesMin' => self::TIME_BLOCK_MINUTES_MIN, 'minutesMax' => self::TIME_BLOCK_MINUTES_MAX],
            'schedule' => ['name' => self::SCHEDULE_NAME_MAX, 'items' => self::SCHEDULE_ITEM_LIMIT],
            'suggestion' => ['title' => self::SUGGESTION_TITLE_MAX, 'body' => self::SUGGESTION_BODY_MAX],
            'achievement' => ['title' => self::ACHIEVEMENT_TITLE_MAX, 'description' => self::DESCRIPTION_MAX],
            'accounting' => [
                'accountName' => self::ACCOUNTING_ACCOUNT_NAME_MAX,
                'categoryName' => self::ACCOUNTING_CATEGORY_NAME_MAX,
                'description' => self::ACCOUNTING_DESCRIPTION_MAX,
                'notes' => self::ACCOUNTING_NOTES_MAX,
                'reference' => self::ACCOUNTING_REFERENCE_MAX,
            ],
            'currencyDecimals' => self::CURRENCY_DECIMALS,
        ];
    }
}
