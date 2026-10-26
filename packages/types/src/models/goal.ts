import type { GoalStatus, GoalTimeframe, GoalTone } from '../generated/contract';
import type { IsoDate, IsoDateTime } from './task';

export interface Goal {
    id: number;
    title: string;
    description: string | null;
    status: GoalStatus;
    timeframe: GoalTimeframe;
    tone: GoalTone;
    start_date: IsoDate | null;
    target_date: IsoDate | null;
    /** Completed ÷ non-cancelled tasks, 0–100. */
    progress: number;
    /** Non-cancelled tasks. */
    tasks_count: number;
    completed_tasks_count: number;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}
