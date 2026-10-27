import type { IsoDate, IsoDateTime } from './task';

/** One entry in a task's work log. */
export interface TaskNote {
    id: number;
    task_id: number;
    logged_on: IsoDate;
    body: string;
    /** Time spent, if recorded. */
    minutes: number | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}
