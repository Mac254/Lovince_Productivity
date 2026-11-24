import type { GoalTone } from '../generated/contract';
import type { IsoDate } from '../models/task';

/** One day of a habit: done in full or in its short version, skipped, missed, or still due today. */
export type HabitDayStatus = 'done' | 'short' | 'skipped' | 'missed' | 'open';

export interface HabitDay {
    date: IsoDate;
    status: HabitDayStatus;
}

export interface Habit {
    /** The schedule slot, e.g. "Exercise", whichever activity it rotated to. */
    title: string;
    type: { id: number; name: string; tone: GoalTone | null } | null;
    /** Still in a schedule, rather than an old slot with history only. */
    scheduled: boolean;
    alternatives: string[];
    done: number;
    planned: number;
    rate: number | null;
    streak: number;
    best_streak: number;
    today: HabitDayStatus | null;
    last_done: string | null;
    days: HabitDay[];
}

export type HabitWindow = 14 | 30 | 90;

export interface HabitSummary {
    days: HabitWindow;
    from: IsoDate;
    to: IsoDate;
    habits: Habit[];
}
