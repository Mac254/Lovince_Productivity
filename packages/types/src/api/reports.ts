import type { GoalTimeframe, TaskCategory, TaskPriority, TaskStatus } from '../generated/contract';
import type { IsoDate, IsoDateTime } from '../models/task';
import type { TaskTypeRef } from '../models/taskType';
import type { GoalTone } from '../generated/contract';

/** Work per type: the user's own types first, then built-in categories for untyped tasks. */
export interface TypeBreakdownRow {
    key: string;
    label: string;
    type_id: number | null;
    category: TaskCategory | null;
    tone: GoalTone | null;
    completed: number;
    open: number;
}

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
    /** Today's plan (due this day or starred); absent on reports made before plans existed. */
    planned?: number;
    planned_completed?: number;
    /** Planned, still open, with work logged that day; absent on older reports. */
    planned_worked?: number;
    /** Work log entries for the day; absent on older reports. */
    notes_logged?: number;
    minutes_logged?: number;
}

export interface ReportTask {
    id: number;
    title: string;
    status: TaskStatus;
    priority: TaskPriority;
    category: TaskCategory;
    type?: TaskTypeRef | null;
    due_date: IsoDate | null;
    goal: { id: number; title: string } | null;
}

export interface ReportWorkLogEntry {
    id: number;
    body: string;
    minutes: number | null;
    task: { id: number; title: string } | null;
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
    /** Completed this day, per type. */
    by_type: TypeBreakdownRow[];
    /** Task notes logged this day, oldest first. */
    work_log?: ReportWorkLogEntry[];
    carried_over: ReportTask[];
    overdue_tasks: ReportTask[];
    blockers_open: ReportBlocker[];
    blockers_resolved: ReportBlocker[];
    goals: ReportGoal[];
    integration: { development_completed: number; integration_pending: number } | null;
    journal: string | null;
}
