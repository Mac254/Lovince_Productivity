import {
    BLOCKER_STATUSES,
    GOAL_STATUSES,
    GOAL_TONES,
    GOAL_TIMEFRAMES,
    TASK_CATEGORIES,
    TASK_PRIORITIES,
    TASK_STATUSES,
    type BlockerStatus,
    type GoalStatus,
    type GoalTone,
    type GoalTimeframe,
    type TaskCategory,
    type TaskPriority,
    type TaskStatus,
} from '@focus/types';

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

function toOptions<T extends string>(values: readonly T[], labels: Record<T, string>) {
    return values.map((value) => ({ value, label: labels[value] }));
}

export const taskStatusOptions = toOptions(TASK_STATUSES, taskStatusLabels);
export const taskPriorityOptions = toOptions(TASK_PRIORITIES, taskPriorityLabels);
export const taskCategoryOptions = toOptions(TASK_CATEGORIES, taskCategoryLabels);
export const goalStatusOptions = toOptions(GOAL_STATUSES, goalStatusLabels);
export const goalTimeframeOptions = toOptions(GOAL_TIMEFRAMES, goalTimeframeLabels);
export const goalToneOptions = toOptions(GOAL_TONES, goalToneLabels);
export const blockerStatusOptions = toOptions(BLOCKER_STATUSES, blockerStatusLabels);
