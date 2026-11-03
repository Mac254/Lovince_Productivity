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

export interface FocusTimerState {
    preset_id: string;
    kind: 'focus' | 'break';
    status: 'idle' | 'running' | 'paused' | 'done';
    duration_ms: number;
    ends_at: number | null;
    paused_remaining_ms: number | null;
    started_at: number | null;
    task: { id: number; title: string } | null;
}

export interface FocusTimerSync {
    state: FocusTimerState | null;
    revision: number;
    server_time: number;
}
