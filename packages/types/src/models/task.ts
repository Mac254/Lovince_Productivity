import type { Cover, GoalTone, TaskCategory, TaskPriority, TaskRecurrence, TaskStatus } from '../generated/contract';
import type { TaskTypeRef } from './taskType';

export type TaskCover = { type: 'gallery'; key: Cover } | { type: 'upload'; url: string };

/** A calendar date in `YYYY-MM-DD` form. */
export type IsoDate = string;

/** A timestamp in ISO 8601 form. */
export type IsoDateTime = string;

export interface GoalRef {
    id: number;
    title: string;
    tone?: GoalTone;
}

export interface Task {
    id: number;
    title: string;
    description: string | null;
    status: TaskStatus;
    /** Why the task was skipped; only set while it is cancelled. */
    cancel_reason: string | null;
    /** The short version of a routine habit, done instead of skipping it. */
    fallback: string | null;
    /** The task was finished in its short version. */
    fallback_done: boolean;
    priority: TaskPriority;
    category: TaskCategory;
    is_highlighted: boolean;
    estimate_minutes: number | null;
    recurrence: TaskRecurrence | null;
    next_occurrence_id: number | null;
    cover: TaskCover | null;
    logged_minutes?: number;
    checklist?: { done: number; total: number };
    start_date: IsoDate | null;
    due_date: IsoDate | null;
    completed_at: IsoDateTime | null;
    is_overdue: boolean;
    task_type_id: number | null;
    type?: TaskTypeRef | null;
    goal_id: number | null;
    goal?: GoalRef | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}
