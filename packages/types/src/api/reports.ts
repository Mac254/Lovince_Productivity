import type { GoalTimeframe, TaskCategory, TaskPriority, TaskStatus } from '../generated/contract';
import type { IsoDate, IsoDateTime } from '../models/task';

export interface ReportSummary {
    completed: number;
    created: number;
    due: number;
    due_completed: number;
    /** `null` when nothing was due. */
    completion_rate: number | null;
    /** Share of completed tasks that had a due date and met it; `null` without any. */
    on_time_rate: number | null;
    overdue: number;
    open_blockers: number;
    blockers_raised: number;
    blockers_resolved: number;
    high_priority_completed: number;
}

export interface ReportTask {
    id: number;
    title: string;
    status: TaskStatus;
    priority: TaskPriority;
    category: TaskCategory;
    due_date: IsoDate | null;
    goal: { id: number; title: string } | null;
}

export interface ReportBlocker {
    id: number;
    description: string;
    task: { id: number; title: string } | null;
    created_at: IsoDateTime | null;
}

export interface ReportGoal {
    id: number;
    title: string;
    timeframe: GoalTimeframe;
    progress: number;
    completed: number;
    total: number;
}

export interface DailyReportSummary {
    date: IsoDate;
    summary: ReportSummary;
    narrative: string;
    generated_at: IsoDateTime;
}

export interface DailyReport extends DailyReportSummary {
    completed_tasks: ReportTask[];
    carried_over: ReportTask[];
    overdue_tasks: ReportTask[];
    blockers_open: ReportBlocker[];
    blockers_resolved: ReportBlocker[];
    goals: ReportGoal[];
    integration: { development_completed: number; integration_pending: number } | null;
    journal: string | null;
}
