import { parseDate, todayIso } from './dates';

/** A month as `YYYY-MM`. */
export type MonthIso = string;

export interface CalendarDay {
    date: string;
    day: number;
    inMonth: boolean;
}

export const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as const;

export const monthOf = (date: string): MonthIso => date.slice(0, 7);

export function addMonths(month: MonthIso, delta: number): MonthIso {
    const d = parseDate(`${month}-01`);
    d.setMonth(d.getMonth() + delta);
    return todayIso(d).slice(0, 7);
}

/** Whole Monday-to-Sunday weeks covering the month (4–6 rows). */
export function monthGrid(month: MonthIso): CalendarDay[][] {
    const first = parseDate(`${month}-01`);
    const start = new Date(first);
    start.setDate(1 - ((first.getDay() + 6) % 7));

    const weeks: CalendarDay[][] = [];
    const cursor = new Date(start);
    do {
        const week: CalendarDay[] = [];
        for (let i = 0; i < 7; i++) {
            week.push({ date: todayIso(cursor), day: cursor.getDate(), inMonth: todayIso(cursor).startsWith(month) });
            cursor.setDate(cursor.getDate() + 1);
        }
        weeks.push(week);
    } while (todayIso(cursor).startsWith(month));

    return weeks;
}

export function shiftDate(date: string, days: number): string {
    const d = parseDate(date);
    d.setDate(d.getDate() + days);
    return todayIso(d);
}
