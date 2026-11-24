import type { BookReading } from '@focus/types';
import { parseDate, todayIso } from '@/lib/dates';

export interface ListeningWeek {
    /** The Monday that starts the week, `YYYY-MM-DD`. */
    start: string;
    minutes: number;
    episodes: number;
}

/** Minutes and episodes per week for the last `weeks` weeks, oldest first, ending with this week. */
export function listeningWeeks(readings: BookReading[], weeks = 12, today = todayIso()): ListeningWeek[] {
    const monday = parseDate(today);
    monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));
    const out: ListeningWeek[] = [];
    for (let i = weeks - 1; i >= 0; i--) {
        const start = new Date(monday);
        start.setDate(monday.getDate() - i * 7);
        out.push({ start: todayIso(start), minutes: 0, episodes: 0 });
    }
    for (const reading of readings) {
        const day = reading.read_on.slice(0, 10);
        const week = [...out].reverse().find((w) => w.start <= day);
        if (!week || day > today) continue;
        week.minutes += reading.minutes ?? 0;
        week.episodes += 1;
    }
    return out;
}

/** Whether an episode was logged as watched or heard to the end. */
export function finished(reading: BookReading): boolean {
    return /^watched to the end/i.test(reading.note ?? '');
}
