import type { TaskPriority, TaskStatus } from '../generated/contract';
import type { IsoDate } from './task';

export interface TimeBlock {
    id: number;
    date: IsoDate;
    start_minute: number;
    minutes: number;
    title: string | null;
    task: { id: number; title: string; status: TaskStatus; priority: TaskPriority } | null;
}

export interface TimeBlockInput {
    date?: IsoDate;
    start_minute?: number;
    minutes?: number;
    title?: string | null;
    task_id?: number | null;
}
