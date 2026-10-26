import type { GoalTone } from '../generated/contract';
import type { IsoDateTime } from './task';

/** A user's own kind of task (Study, Personal, Review…), coloured with a goal tone. */
export interface TaskType {
    id: number;
    name: string;
    tone: GoalTone;
    tasks_count?: number;
    created_at: IsoDateTime;
}

export type TaskTypeRef = Pick<TaskType, 'id' | 'name' | 'tone'>;
