import type { GoalTone, ReviewPeriod } from '../generated/contract';
import type { IsoDate, IsoDateTime, Task } from '../models/task';

/** One bar on the review chart: a day for a week or month, a month for a year. */
export interface ReviewBucket {
    start: IsoDate;
    end: IsoDate;
    completed: number;
    minutes_logged: number;
    focus_minutes: number;
}

export interface ReviewGoal {
    id: number;
    title: string;
    tone: GoalTone;
    progress: number;
    completed: number;
    total: number;
    completed_in_period: number;
}

export interface Reflection {
    wins: string | null;
    challenges: string | null;
    next_focus: string | null;
    completed_at: IsoDateTime | null;
    updated_at: IsoDateTime | null;
}

/** A week's review inside a month, or a month's inside a year. */
export interface ShorterReview extends Reflection {
    period: ReviewPeriod;
    start: IsoDate;
    label: string;
}

export interface ReviewInput {
    wins?: string | null;
    challenges?: string | null;
    next_focus?: string | null;
    completed?: boolean;
}

export interface Review {
    period: { kind: ReviewPeriod; label: string; start: IsoDate; end: IsoDate; next_start: IsoDate; next_end: IsoDate };
    totals: {
        completed: number;
        minutes_logged: number;
        focus_minutes: number;
        focus_sessions: number;
        blockers_opened: number;
        blockers_resolved: number;
        blockers_open: number;
    };
    buckets: ReviewBucket[];
    /** Every task for a week; the latest 100 for a month or year (`totals.completed` counts them all). */
    completed: Task[];
    carry_over: Task[];
    skipped: Task[];
    next: Task[];
    unscheduled: Task[];
    goals: ReviewGoal[];
    journal: { date: IsoDate; excerpt: string }[];
    review: Reflection | null;
    shorter: ShorterReview[];
}

export interface ReviewSummary {
    period: ReviewPeriod;
    start: IsoDate;
    label: string;
    completed_at: IsoDateTime;
}

export interface ReviewPrompt {
    week_start: IsoDate;
    due: boolean;
    done: boolean;
}
