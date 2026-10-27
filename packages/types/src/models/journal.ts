import type { IsoDate, IsoDateTime } from './task';

export interface JournalEntry {
    id: number;
    date: IsoDate;
    body: string;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

type TaskRef = { id: number; title: string };

/** What happened on a day, gathered for writing about it. */
export interface JournalContext {
    date: IsoDate;
    completed: TaskRef[];
    work_log: { id: number; body: string; minutes: number | null; task: TaskRef | null }[];
    blockers: { id: number; description: string; resolved: boolean; task: TaskRef | null }[];
    /** Due that day and still open when it ended (or now, for today). */
    still_open: TaskRef[];
}
