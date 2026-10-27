import type { TaskCategory, TaskPriority } from '../generated/contract';
import type { IsoDate } from '../models/task';
import type { ReportGoal, TypeBreakdownRow } from './reports';

export type MetricRange = 7 | 30 | 90;

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
    };
    /** `logged` counts work log entries for the day; `minutes` is the time recorded on them. */
    daily: { date: IsoDate; created: number; completed: number; logged: number; minutes: number }[];
    /** `count` is `completed` tasks plus `logged` work log entries for the day. */
    heatmap: { date: IsoDate; count: number; completed: number; logged: number }[];
    by_priority: { key: TaskPriority; completed: number; open: number }[];
    by_category: { key: TaskCategory; completed: number; open: number }[];
    by_type: TypeBreakdownRow[];
    goals: ReportGoal[];
}
