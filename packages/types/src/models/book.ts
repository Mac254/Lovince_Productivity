import type { BookKind, BookStatus, TaskRecurrence, TaskStatus } from '../generated/contract';
import type { GoalRef, IsoDate, IsoDateTime } from './task';

export interface BookReading {
    id: number;
    book_id: number;
    read_on: IsoDate;
    /** A podcast episode's name. */
    episode: string | null;
    chapters: number;
    minutes: number | null;
    note: string | null;
    /** How a podcast episode is changing me. */
    impact: string | null;
    created_at: IsoDateTime;
}

export interface BookTask {
    id: number;
    title: string;
    status: TaskStatus;
    due_date: IsoDate | null;
    recurrence: TaskRecurrence | null;
}

export interface Book {
    id: number;
    kind: BookKind;
    title: string;
    author: string | null;
    /** Where to find it: a podcast's channel, a book's page. */
    link: string | null;
    /** The uploaded cover or picture, served by the API. */
    image_url: string | null;
    status: BookStatus;
    chapters_total: number | null;
    chapters_read: number;
    /** 0–100 from chapters read, 100 once finished, null without a chapter count. */
    progress: number | null;
    started_on: IsoDate | null;
    finished_on: IsoDate | null;
    rating: number | null;
    notes: string | null;
    goal_id: number | null;
    goal?: GoalRef | null;
    minutes_read?: number;
    last_read_on?: IsoDate | null;
    /** Readings, or a podcast's episodes, logged. */
    sessions?: number;
    open_tasks?: number;
    /** Only on a single book. */
    readings?: BookReading[];
    tasks?: BookTask[];
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}
