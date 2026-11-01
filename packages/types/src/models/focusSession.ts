import type { IsoDate, IsoDateTime } from './task';

export interface FocusSession {
    id: number;
    started_on: IsoDate;
    started_at: IsoDateTime;
    ended_at: IsoDateTime;
    planned_minutes: number;
    focused_seconds: number;
    minutes: number;
    completed: boolean;
    task: { id: number; title: string } | null;
    task_note_id: number | null;
}

export interface FocusDay {
    date: IsoDate;
    minutes: number;
    count: number;
}

export interface FocusHistory {
    data: FocusSession[];
    meta: {
        date: IsoDate;
        minutes: number;
        count: number;
        completed: number;
        goal_minutes: number;
        streak: number;
        week: FocusDay[];
    };
}

export interface FocusSessionInput {
    started_at: IsoDateTime;
    started_on: IsoDate;
    planned_minutes: number;
    focused_seconds: number;
    completed: boolean;
    task_id: number | null;
}
