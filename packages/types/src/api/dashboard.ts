import type { Blocker } from '../models/blocker';
import type { Goal } from '../models/goal';
import type { Quote } from '../models/quote';
import type { IsoDate, Task } from '../models/task';

export interface DashboardStats {
    due_today: number;
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
    overdue_tasks: Task[];
    completed_today_tasks: Task[];
    upcoming_tasks: Task[];
    highlighted_tasks: Task[];
    open_blockers: Blocker[];
    recent_activity: Task[];
    integration: IntegrationSummary;
    weekly_goals: Goal[];
    active_goals: Goal[];
    quote: Quote | null;
}
