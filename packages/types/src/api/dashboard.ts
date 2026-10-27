import type { Blocker } from '../models/blocker';
import type { Goal } from '../models/goal';
import type { Quote } from '../models/quote';
import type { IsoDate, Task } from '../models/task';
import type { TypeBreakdownRow } from './reports';

export interface DashboardStats {
    due_today: number;
    /** Open tasks due today or highlighted: today's plan. */
    planned_today: number;
    /** Completed today and in today's plan; with `planned_today`, the plan's progress. */
    planned_completed_today: number;
    /** In today's plan, still open, with work logged today: counts as half done in the plan's progress. */
    planned_worked_today: number;
    overdue: number;
    in_progress: number;
    open: number;
    completed_today: number;
    completed_this_week: number;
    completed_total: number;
    active_goals: number;
    open_blockers: number;
    week_due_total: number;
    week_due_completed: number;
    /** `null` when nothing is due this week. */
    weekly_completion_rate: number | null;
}

export interface IntegrationSummary {
    development_completed: number;
    development_open: number;
    integration_completed: number;
    integration_pending: Task[];
}

export interface Dashboard {
    date: IsoDate;
    week: { start: IsoDate; end: IsoDate };
    stats: DashboardStats;
    today_tasks: Task[];
    planned_tasks: Task[];
    overdue_tasks: Task[];
    completed_today_tasks: Task[];
    upcoming_tasks: Task[];
    highlighted_tasks: Task[];
    open_blockers: Blocker[];
    recent_activity: Task[];
    /** Open now and completed this week, per type. */
    by_type: TypeBreakdownRow[];
    integration: IntegrationSummary;
    weekly_goals: Goal[];
    active_goals: Goal[];
    quote: Quote | null;
}
