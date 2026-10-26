import type { TaskCategory, TaskPriority } from '../generated/contract';
import type { IsoDate } from '../models/task';
import type { ReportGoal } from './reports';

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
    };
    daily: { date: IsoDate; created: number; completed: number }[];
    heatmap: { date: IsoDate; count: number }[];
    by_priority: { key: TaskPriority; completed: number; open: number }[];
    by_category: { key: TaskCategory; completed: number; open: number }[];
    goals: ReportGoal[];
}
