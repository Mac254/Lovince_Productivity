import { shiftDate } from '@/lib/calendar';
import { parseDate, todayIso } from '@/lib/dates';

export function weekStartIso(iso: string = todayIso()): string {
    return shiftDate(iso, -((parseDate(iso).getDay() + 6) % 7));
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
