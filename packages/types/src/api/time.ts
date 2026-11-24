import type { GoalTone } from '../generated/contract';
import type { IsoDate } from '../models/task';

export type TimeRange = 'day' | 'week' | 'month';

export interface TimeCategory {
    /** The task type's id, or `other` for time with no type. */
    key: string;
    name: string;
    tone: GoalTone | null;
    minutes: number;
}

export interface TimeBucket {
    /** The hour (`0`–`23`) on a day, otherwise the date. */
    label: string;
    minutes: Record<string, number>;
}

export interface TimeActivity {
    title: string;
    key: string;
    minutes: number;
    days: number;
}

export interface TimeSummary {
    range: TimeRange;
    from: IsoDate;
    to: IsoDate;
    total: number;
    previous_total: number;
    daily_average: number;
    previous_average: number;
    focus_minutes: number;
    categories: TimeCategory[];
    buckets: TimeBucket[];
    activities: TimeActivity[];
}
