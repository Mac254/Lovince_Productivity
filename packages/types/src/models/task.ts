import type { GoalTone, TaskCategory, TaskPriority, TaskStatus } from '../generated/contract';
import type { TaskTypeRef } from './taskType';

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
    priority: TaskPriority;
    category: TaskCategory;
    is_highlighted: boolean;
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
