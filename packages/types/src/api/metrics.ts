import type { TaskCategory, TaskPriority } from '../generated/contract';
import type { IsoDate } from '../models/task';
import type { ReportGoal, TypeBreakdownRow } from './reports';

export type MetricRange = 7 | 30 | 90;

export interface FocusMetrics {
    minutes: number;
    minutes_previous: number;
    sessions: number;
    completed: number;
    per_day: number;
    streak: number;
    goal_minutes: number;
    goal_days: number;
    best_hour: number | null;
    by_hour: number[];
}

export interface MetricInsight {
    key: string;
    tone: 'positive' | 'neutral' | 'warning';
    text: string;
}

export interface ActivityStreak {
    current: number;
    best: number;
    active_today: boolean;
    active_days: number;
}

export interface Metrics {
    range: { days: MetricRange; start: IsoDate; end: IsoDate };
    totals: {
        completed: number;
        completed_previous: number;
        created: number;
        per_day: number;
        on_time_rate: number | null;
        on_time_sample: number;
        median_cycle_days: number | null;
        open_now: number;
        overdue_now: number;
        open_blockers: number;
        blockers_resolved: number;
        /** Work log entries dated in the range, and the minutes recorded on them. */
        entries_logged: number;
        minutes_logged: number;
        minutes_logged_previous: number;
        estimated_tasks: number;
        estimated_minutes: number;
        estimated_logged_minutes: number;
        estimate_accuracy: number | null;
    };
    /** `logged` counts work log entries for the day; `minutes` is the time recorded on them. */
    daily: { date: IsoDate; created: number; completed: number; logged: number; minutes: number; focus: number }[];
    focus: FocusMetrics;
    heatmap: { date: IsoDate; count: number; completed: number; logged: number; focus_minutes: number }[];
    consistency: ActivityStreak & { days: number };
    insights: MetricInsight[];
    by_priority: { key: TaskPriority; completed: number; open: number }[];
    by_category: { key: TaskCategory; completed: number; open: number }[];
    by_type: TypeBreakdownRow[];
    goals: ReportGoal[];
}
