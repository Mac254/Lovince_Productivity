import type { Schedule } from '@focus/types';

export type DayPreset = 'every' | 'weekdays' | 'weekends';

export const dayPresets: { value: DayPreset; label: string; days: number[] }[] = [
    { value: 'every', label: 'Every day', days: [] },
    { value: 'weekdays', label: 'Weekdays', days: [1, 2, 3, 4, 5] },
    { value: 'weekends', label: 'Weekends', days: [6, 7] },
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
