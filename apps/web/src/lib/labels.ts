import {
    BLOCKER_STATUSES,
    GOAL_STATUSES,
    GOAL_TONES,
    GOAL_TIMEFRAMES,
    SUGGESTION_CATEGORIES,
    SUGGESTION_SOURCES,
    TASK_CATEGORIES,
    TASK_PRIORITIES,
    TASK_RECURRENCES,
    TASK_STATUSES,
    type BlockerStatus,
    type GoalStatus,
    type GoalTone,
    type GoalTimeframe,
    type SuggestionCategory,
    type SuggestionSource,
    type SuggestionStatus,
    type TaskCategory,
    type TaskPriority,
    type TaskRecurrence,
    type TaskStatus,
} from '@focus/types';
import type { BadgeTone, IconName } from '@focus/ui';

export const taskStatusLabels: Record<TaskStatus, string> = {
    not_started: 'Not started',
    in_progress: 'In progress',
    blocked: 'Blocked',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

export const taskPriorityLabels: Record<TaskPriority, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
    urgent: 'Urgent',
};

export const taskPriorityTones: Record<TaskPriority, BadgeTone> = {
    low: 'neutral',
    medium: 'info',
    high: 'warning',
    urgent: 'danger',
};

/** 1–4, following the order the API defines priorities in. */
export const taskPriorityRank = Object.fromEntries(TASK_PRIORITIES.map((p, i) => [p, i + 1])) as Record<
    TaskPriority,
    number
>;

export const taskCategoryLabels: Record<TaskCategory, string> = {
    general: 'General',
    development: 'Development',
    integration: 'Integration',
};

export const goalStatusLabels: Record<GoalStatus, string> = {
    active: 'Active',
    on_hold: 'On hold',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

export const goalStatusTones: Record<GoalStatus, BadgeTone> = {
    active: 'success',
    on_hold: 'warning',
    completed: 'info',
    cancelled: 'neutral',
};

export const goalTimeframeLabels: Record<GoalTimeframe, string> = {
    weekly: 'Weekly',
    long_term: 'Long-term',
};

export const goalToneLabels: Record<GoalTone, string> = {
    green: 'Green',
    blue: 'Blue',
    amber: 'Amber',
    violet: 'Violet',
    rose: 'Rose',
    cyan: 'Cyan',
};

export const blockerStatusLabels: Record<BlockerStatus, string> = {
    open: 'Open',
    resolved: 'Resolved',
};

export const taskRecurrenceLabels: Record<TaskRecurrence, string> = {
    daily: 'Every day',
    weekdays: 'Every weekday',
    weekly: 'Every week',
    monthly: 'Every month',
};

export const suggestionCategoryLabels: Record<SuggestionCategory, string> = {
    focus: 'Focus',
    growth: 'Growth',
    health: 'Health',
    wellbeing: 'Wellbeing',
    social: 'Social',
    fun: 'Fun',
};

export const suggestionCategoryTones: Record<SuggestionCategory, BadgeTone> = {
    focus: 'info',
    growth: 'accent',
    health: 'success',
    wellbeing: 'neutral',
    social: 'warning',
    fun: 'danger',
};

export const suggestionCategoryIcons: Record<SuggestionCategory, IconName> = {
    focus: 'target',
    growth: 'flame',
    health: 'check-circle',
    wellbeing: 'sun',
    social: 'user',
    fun: 'star',
};

export const suggestionStatusLabels: Record<SuggestionStatus, string> = {
    new: 'New',
    accepted: 'Accepted',
    done: 'Done',
    dismissed: 'Dismissed',
};

export const suggestionSourceLabels: Record<SuggestionSource, string> = {
    insight: 'From your data',
    library: 'Idea',
    custom: 'Yours',
};

export const suggestionStatusTones: Record<SuggestionStatus, BadgeTone> = {
    new: 'highlight',
    accepted: 'info',
    done: 'success',
    dismissed: 'neutral',
};

export const suggestionStatusIcons: Record<SuggestionStatus, IconName> = {
    new: 'lightbulb',
    accepted: 'calendar-check',
    done: 'check-circle',
    dismissed: 'close-circle',
};

function toOptions<T extends string>(values: readonly T[], labels: Record<T, string>) {
    return values.map((value) => ({ value, label: labels[value] }));
}

export const taskStatusOptions = toOptions(TASK_STATUSES, taskStatusLabels);
export const taskPriorityOptions = toOptions(TASK_PRIORITIES, taskPriorityLabels);
export const taskRecurrenceOptions = [
    { value: '' as const, label: 'Doesn’t repeat' },
    ...toOptions(TASK_RECURRENCES, taskRecurrenceLabels),
];
export const taskCategoryOptions = toOptions(TASK_CATEGORIES, taskCategoryLabels);
export const goalStatusOptions = toOptions(GOAL_STATUSES, goalStatusLabels);
export const goalTimeframeOptions = toOptions(GOAL_TIMEFRAMES, goalTimeframeLabels);
export const goalToneOptions = toOptions(GOAL_TONES, goalToneLabels);
export const blockerStatusOptions = toOptions(BLOCKER_STATUSES, blockerStatusLabels);
export const suggestionCategoryOptions = toOptions(SUGGESTION_CATEGORIES, suggestionCategoryLabels);
export const suggestionSourceOptions = toOptions(SUGGESTION_SOURCES, suggestionSourceLabels);
