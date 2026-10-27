const DAY_MS = 86_400_000;

/** Parses a `YYYY-MM-DD` string as a local calendar date, avoiding UTC shifts. */
export function parseDate(value: string): Date {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y ?? 1970, (m ?? 1) - 1, d ?? 1);
}

export function todayIso(now = new Date()): string {
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

export function formatDate(
    value: string | null,
    opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short' },
): string {
    if (!value) return '';
    const date = value.length === 10 ? parseDate(value) : new Date(value);
    return new Intl.DateTimeFormat(undefined, opts).format(date);
}

export function formatLongDate(value: string): string {
    return formatDate(value, { weekday: 'long', day: 'numeric', month: 'long' });
}

export function daysUntil(value: string, today = todayIso()): number {
    return Math.round((parseDate(value).getTime() - parseDate(today).getTime()) / DAY_MS);
}

export function dueLabel(value: string | null, today = todayIso()): string {
    if (!value) return 'No due date';
    const diff = daysUntil(value, today);
    if (diff === 0) return 'Due today';
    if (diff === 1) return 'Due tomorrow';
    if (diff === -1) return 'Due yesterday';
    if (diff < 0) return `${-diff} days overdue`;
    if (diff < 7) return `Due in ${diff} days`;
    return `Due ${formatDate(value)}`;
}

export function relativeTime(iso: string, now = Date.now()): string {
    const seconds = Math.round((new Date(iso).getTime() - now) / 1000);
    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86_400],
        ['hour', 3_600],
        ['minute', 60],
    ];
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) return rtf.format(Math.round(seconds / size), unit);
    }
    return 'just now';
}

export function greeting(hour = new Date().getHours()): string {
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
}

/** ISO 8601 week number (weeks start on Monday; week 1 holds the year's first Thursday). */
export function isoWeek(date: Date): number {
    const day = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    day.setDate(day.getDate() + 3 - ((day.getDay() + 6) % 7));
    const firstThursday = new Date(day.getFullYear(), 0, 4);
    return (
        1 +
        Math.round(
            ((day.getTime() - firstThursday.getTime()) / 86_400_000 - 3 + ((firstThursday.getDay() + 6) % 7)) / 7,
        )
    );
}

/** Sunday of the current week, matching the API's Monday–Sunday weeks. */
export function endOfWeekIso(now = new Date()): string {
    const date = new Date(now);
    date.setDate(date.getDate() + ((7 - date.getDay()) % 7));
    return todayIso(date);
}

/** `95` → `1h 35m`; `0`/`null` → `''`. */
export function formatMinutes(minutes: number | null | undefined): string {
    if (!minutes) return '';
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return [hours ? `${hours}h` : '', rest ? `${rest}m` : ''].filter(Boolean).join(' ');
}
