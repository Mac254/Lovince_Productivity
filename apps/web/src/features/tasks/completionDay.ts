import type { IsoDate, Task } from '@focus/types';
import { todayIso } from '@/lib/dates';

export const localDay = (iso: string): IsoDate => todayIso(new Date(iso));

/** The earliest and latest days a task can be marked done on, as local calendar dates. */
export function completionRange(task: Pick<Task, 'created_at'>, today = todayIso()): { min: IsoDate; max: IsoDate } {
    return { min: localDay(task.created_at), max: today };
}

/** Suggests the day a missed task was likely done: its due day, kept inside the allowed range. */
export function suggestedCompletionDay(task: Pick<Task, 'created_at' | 'due_date'>, today = todayIso()): IsoDate {
    const { min, max } = completionRange(task, today);
    const day = task.due_date ?? max;
    if (day < min) return min;
    return day > max ? max : day;
}
