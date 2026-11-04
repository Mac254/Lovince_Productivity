<?php

namespace App\Modules\Backup\Services;

use App\Modules\Blockers\Enums\BlockerStatus;
use App\Modules\Goals\Enums\GoalStatus;
use App\Modules\Goals\Enums\GoalTimeframe;
use App\Modules\Goals\Enums\GoalTone;
use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Suggestions\Enums\SuggestionSource;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Tasks\Enums\TaskCategory;
use App\Modules\Tasks\Enums\TaskPriority;
use App\Modules\Tasks\Enums\TaskRecurrence;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Support\Contract;
use Illuminate\Validation\Rule;

class BackupRules
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $list = ['sometimes', 'array', 'max:'.Contract::BACKUP_RECORDS_MAX];
        $ref = ['required', 'integer'];
        $optionalRef = ['nullable', 'integer'];
        $date = ['nullable', 'date_format:Y-m-d'];
        $time = ['nullable', 'date'];

        $settings = ['settings' => ['sometimes', 'array']];
        foreach ($this->settings->rules() as $key => $rule) {
            $settings["settings.{$key}"] = $rule;
        }

        return [
            'format' => ['required', 'in:'.BackupExporter::FORMAT],
            'version' => ['required', 'integer', Rule::in(BackupExporter::VERSIONS)],
            ...$settings,

            'task_types' => ['sometimes', 'array', 'max:'.Contract::TASK_TYPE_LIMIT],
            'task_types.*.ref' => $ref,
            'task_types.*.name' => ['required', 'string', 'max:'.Contract::TASK_TYPE_NAME_MAX],
            'task_types.*.tone' => ['required', Rule::enum(GoalTone::class)],

            'goals' => $list,
            'goals.*.ref' => $ref,
            'goals.*.title' => ['required', 'string', 'max:'.Contract::GOAL_TITLE_MAX],
            'goals.*.description' => ['nullable', 'string', 'max:'.Contract::DESCRIPTION_MAX],
            'goals.*.status' => ['required', Rule::enum(GoalStatus::class)],
            'goals.*.timeframe' => ['required', Rule::enum(GoalTimeframe::class)],
            'goals.*.tone' => ['required', Rule::enum(GoalTone::class)],
            'goals.*.start_date' => $date,
            'goals.*.target_date' => $date,

            'tasks' => $list,
            'tasks.*.ref' => $ref,
            'tasks.*.goal_ref' => $optionalRef,
            'tasks.*.task_type_ref' => $optionalRef,
            'tasks.*.title' => ['required', 'string', 'max:'.Contract::TASK_TITLE_MAX],
            'tasks.*.description' => ['nullable', 'string', 'max:'.Contract::DESCRIPTION_MAX],
            'tasks.*.status' => ['required', Rule::enum(TaskStatus::class)],
            'tasks.*.priority' => ['required', Rule::enum(TaskPriority::class)],
            'tasks.*.category' => ['required', Rule::enum(TaskCategory::class)],
            'tasks.*.recurrence' => ['nullable', Rule::enum(TaskRecurrence::class)],
            'tasks.*.is_highlighted' => ['boolean'],
            'tasks.*.cover' => ['nullable', Rule::in(Contract::COVERS)],
            'tasks.*.estimate_minutes' => ['nullable', 'integer', 'min:1', 'max:'.Contract::TASK_ESTIMATE_MINUTES_MAX],
            'tasks.*.start_date' => $date,
            'tasks.*.due_date' => $date,
            'tasks.*.completed_at' => $time,
            'tasks.*.created_at' => $time,
            'tasks.*.notes' => ['sometimes', 'array'],
            'tasks.*.notes.*.logged_on' => ['required', 'date_format:Y-m-d'],
            'tasks.*.notes.*.body' => ['required', 'string', 'max:'.Contract::TASK_NOTE_MAX],
            'tasks.*.notes.*.minutes' => ['nullable', 'integer', 'min:1', 'max:'.Contract::TASK_NOTE_MINUTES_MAX],
            'tasks.*.checklist' => ['sometimes', 'array', 'max:'.Contract::CHECKLIST_LIMIT],
            'tasks.*.checklist.*.title' => ['required', 'string', 'max:'.Contract::CHECKLIST_ITEM_MAX],
            'tasks.*.checklist.*.done' => ['boolean'],

            'blockers' => $list,
            'blockers.*.task_ref' => $optionalRef,
            'blockers.*.description' => ['required', 'string', 'max:'.Contract::BLOCKER_MAX],
            'blockers.*.status' => ['required', Rule::enum(BlockerStatus::class)],
            'blockers.*.resolved_at' => $time,

            'journal' => $list,
            'journal.*.date' => ['required', 'date_format:Y-m-d'],
            'journal.*.body' => ['required', 'string', 'max:'.Contract::JOURNAL_MAX],

            'focus_sessions' => $list,
            'focus_sessions.*.task_ref' => $optionalRef,
            'focus_sessions.*.started_on' => ['required', 'date_format:Y-m-d'],
            'focus_sessions.*.started_at' => ['required', 'date'],
            'focus_sessions.*.ended_at' => ['required', 'date'],
            'focus_sessions.*.planned_minutes' => ['required', 'integer', 'min:1', 'max:'.Contract::FOCUS_SESSION_MINUTES_MAX],
            'focus_sessions.*.focused_seconds' => ['required', 'integer', 'min:0', 'max:'.(Contract::FOCUS_SESSION_MINUTES_MAX * 60)],
            'focus_sessions.*.completed' => ['boolean'],

            'weekly_reviews' => $list,
            'weekly_reviews.*.week_start' => ['required', 'date_format:Y-m-d'],
            'weekly_reviews.*.wins' => ['nullable', 'string', 'max:'.Contract::REVIEW_MAX],
            'weekly_reviews.*.challenges' => ['nullable', 'string', 'max:'.Contract::REVIEW_MAX],
            'weekly_reviews.*.next_focus' => ['nullable', 'string', 'max:'.Contract::REVIEW_MAX],
            'weekly_reviews.*.completed_at' => $time,

            'time_blocks' => $list,
            'time_blocks.*.task_ref' => $optionalRef,
            'time_blocks.*.title' => ['nullable', 'string', 'max:'.Contract::TIME_BLOCK_TITLE_MAX],
            'time_blocks.*.date' => ['required', 'date_format:Y-m-d'],
            'time_blocks.*.start_minute' => ['required', 'integer', 'min:0', 'max:1439'],
            'time_blocks.*.minutes' => ['required', 'integer', 'min:'.Contract::TIME_BLOCK_MINUTES_MIN, 'max:'.Contract::TIME_BLOCK_MINUTES_MAX],

            'schedules' => $list,
            'schedules.*.ref' => $ref,
            'schedules.*.name' => ['required', 'string', 'max:'.Contract::SCHEDULE_NAME_MAX],
            'schedules.*.days' => ['nullable', 'array'],
            'schedules.*.days.*' => ['integer', 'between:1,7'],
            'schedules.*.items' => ['sometimes', 'array', 'max:'.Contract::SCHEDULE_ITEM_LIMIT],
            'schedules.*.items.*.task_type_ref' => $optionalRef,
            'schedules.*.items.*.goal_ref' => $optionalRef,
            'schedules.*.items.*.title' => ['required', 'string', 'max:'.Contract::TIME_BLOCK_TITLE_MAX],
            'schedules.*.items.*.start_minute' => ['required', 'integer', 'min:0', 'max:1439'],
            'schedules.*.items.*.minutes' => ['required', 'integer', 'min:'.Contract::TIME_BLOCK_MINUTES_MIN, 'max:'.Contract::TIME_BLOCK_MINUTES_MAX],

            'suggestions' => $list,
            'suggestions.*.task_ref' => $optionalRef,
            'suggestions.*.source' => ['required', Rule::enum(SuggestionSource::class)],
            'suggestions.*.key' => ['nullable', 'string', 'max:255'],
            'suggestions.*.title' => ['required', 'string', 'max:'.Contract::SUGGESTION_TITLE_MAX],
            'suggestions.*.body' => ['nullable', 'string', 'max:'.Contract::SUGGESTION_BODY_MAX],
            'suggestions.*.category' => ['required', Rule::enum(SuggestionCategory::class)],
            'suggestions.*.status' => ['required', Rule::enum(SuggestionStatus::class)],
            'suggestions.*.action' => ['nullable', 'array'],
            'suggestions.*.accepted_at' => $time,
            'suggestions.*.done_at' => $time,
            'suggestions.*.dismissed_at' => $time,

            'achievements' => $list,
            'achievements.*.goal_ref' => $optionalRef,
            'achievements.*.task_ref' => $optionalRef,
            'achievements.*.title' => ['required', 'string', 'max:'.Contract::ACHIEVEMENT_TITLE_MAX],
            'achievements.*.description' => ['nullable', 'string', 'max:'.Contract::DESCRIPTION_MAX],
            'achievements.*.achieved_on' => ['required', 'date_format:Y-m-d'],
            'achievements.*.category' => ['nullable', Rule::enum(SuggestionCategory::class)],
        ];
    }
}
