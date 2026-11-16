import type { ReviewPeriod } from '@focus/types';
import { addMonths, shiftDate } from '@/lib/calendar';
import { parseDate, todayIso } from '@/lib/dates';

export const PERIOD_NAMES: Record<ReviewPeriod, string> = { week: 'week', month: 'month', year: 'year' };

export function weekStartIso(iso: string = todayIso()): string {
    return shiftDate(iso, -((parseDate(iso).getDay() + 6) % 7));
}

/** The first day of the week (Monday), month or year that contains the date. */
export function periodStart(period: ReviewPeriod, iso: string = todayIso()): string {
    if (period === 'week') return weekStartIso(iso);
    if (period === 'month') return `${iso.slice(0, 7)}-01`;
    return `${iso.slice(0, 4)}-01-01`;
}

/** The start of the period `delta` periods away. */
export function shiftPeriod(period: ReviewPeriod, start: string, delta: number): string {
    if (period === 'week') return shiftDate(start, delta * 7);
    if (period === 'month') return `${addMonths(start.slice(0, 7), delta)}-01`;
    return `${Number(start.slice(0, 4)) + delta}-01-01`;
}

export function weekLabel(start: string, end: string): string {
    const from = parseDate(start);
    const to = parseDate(end);
    const first = from.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
    const last = to.toLocaleDateString(
        undefined,
        from.getMonth() === to.getMonth() ? { day: 'numeric' } : { day: 'numeric', month: 'short' },
    );
    return `${first} – ${last}, ${to.getFullYear()}`;
}

export function periodLabel(period: ReviewPeriod, start: string, end: string): string {
    if (period === 'week') return weekLabel(start, end);
    if (period === 'month') return parseDate(start).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    return start.slice(0, 4);
}
