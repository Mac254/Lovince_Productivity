import type { Schedule } from '@focus/types';

export const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as const;

export type DayPreset = 'every' | 'weekdays' | 'weekends' | `day-${number}`;

export const dayPresets: { value: DayPreset; label: string; days: number[] }[] = [
    { value: 'every', label: 'Every day', days: [] },
    { value: 'weekdays', label: 'Weekdays', days: [1, 2, 3, 4, 5] },
    { value: 'weekends', label: 'Weekends', days: [6, 7] },
    ...weekdays.map((name, index) => ({
        value: `day-${index + 1}` as DayPreset,
        label: name.slice(0, 3),
        days: [index + 1],
    })),
];

export function presetOf(days: number[]): DayPreset {
    const key = [...days].sort().join();
    return dayPresets.find((preset) => preset.days.join() === key)?.value ?? 'every';
}

export function isoWeekday(date: string): number {
    const day = new Date(`${date}T00:00:00`).getDay();
    return day === 0 ? 7 : day;
}

export function fitsDay(schedule: Schedule, date: string): boolean {
    return !schedule.days.length || schedule.days.includes(isoWeekday(date));
}

export function scheduleFor(schedules: Schedule[], date: string): Schedule | undefined {
    return schedules
        .filter((schedule) => fitsDay(schedule, date))
        .sort((a, b) => (a.days.length || 8) - (b.days.length || 8))[0];
}

export type DaySection = 'morning' | 'afternoon' | 'evening';

export const daySections: { value: DaySection; label: string; from: number }[] = [
    { value: 'morning', label: 'Morning', from: 0 },
    { value: 'afternoon', label: 'Afternoon', from: 12 * 60 },
    { value: 'evening', label: 'Evening', from: 17 * 60 },
];

export function sectionOf(minute: number): DaySection {
    return [...daySections].reverse().find((section) => minute >= section.from)?.value ?? 'morning';
}
