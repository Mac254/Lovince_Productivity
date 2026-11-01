import type { GoalTone } from '../generated/contract';
import type { IsoDate, IsoDateTime, Task } from '../models/task';

export interface WeeklyReviewDay {
    date: IsoDate;
    completed: number;
    minutes_logged: number;
    focus_minutes: number;
}

export interface WeeklyReviewGoal {
    id: number;
    title: string;
    tone: GoalTone;
    progress: number;
    completed: number;
    total: number;
    completed_this_week: number;
}

export interface WeeklyReflection {
    wins: string | null;
    challenges: string | null;
    next_focus: string | null;
    completed_at: IsoDateTime | null;
    updated_at: IsoDateTime | null;
}

export interface WeeklyReviewInput {
    wins?: string | null;
    challenges?: string | null;
    next_focus?: string | null;
    completed?: boolean;
}

export interface WeeklyReview {
    week: { start: IsoDate; end: IsoDate; next_start: IsoDate; next_end: IsoDate };
    totals: {
        completed: number;
        minutes_logged: number;
        focus_minutes: number;
        focus_sessions: number;
        blockers_opened: number;
        blockers_resolved: number;
        blockers_open: number;
    };
    days: WeeklyReviewDay[];
    completed: Task[];
    carry_over: Task[];
    next_week: Task[];
    unscheduled: Task[];
    goals: WeeklyReviewGoal[];
    journal: { date: IsoDate; excerpt: string }[];
    review: WeeklyReflection | null;
}

export interface WeeklyReviewSummary {
    week_start: IsoDate;
    completed_at: IsoDateTime;
}

export interface ReviewPrompt {
    week_start: IsoDate;
    due: boolean;
    done: boolean;
}
