import type { DashboardStats } from '@focus/types';

export interface DayProgress {
    done: number;
    /** Planned tasks still open but worked on today. */
    worked: number;
    remaining: number;
    total: number;
    percent: number;
}

/** Today's plan progress: a completed task counts fully, an open one with work logged today counts half. */
export function dayProgress(stats: DashboardStats): DayProgress {
    const done = stats.planned_completed_today;
    const worked = stats.planned_worked_today;
    const remaining = stats.planned_today;
    const total = done + remaining;
    const percent = total ? Math.round(((done + worked / 2) / total) * 100) : 0;
    return { done, worked, remaining, total, percent };
}

/** “2 of 5 done · 1 worked on”, or only the first part when nothing was worked on. */
export function dayProgressLabel(day: DayProgress): string {
    const base = `${day.done} of ${day.total} done`;
    return day.worked ? `${base} · ${day.worked} worked on` : base;
}
