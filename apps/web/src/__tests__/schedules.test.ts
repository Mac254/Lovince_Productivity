import type { Schedule } from '@focus/types';
import { describe, expect, it } from 'vitest';
import { dayPresets, isoWeekday, presetOf, scheduleFor } from '@/features/planner/schedules';

const schedule = (id: number, name: string, days: number[]): Schedule => ({
    id,
    name,
    days,
    items: [],
    updated_at: null,
});

describe('schedule days', () => {
    it('offers single weekdays alongside the groups', () => {
        expect(dayPresets.map((preset) => preset.label)).toEqual([
            'Every day',
            'Weekdays',
            'Weekends',
            'Mon',
            'Tue',
            'Wed',
            'Thu',
            'Fri',
            'Sat',
            'Sun',
        ]);
        expect(presetOf([2])).toBe('day-2');
        expect(presetOf([7, 6])).toBe('weekends');
        expect(presetOf([])).toBe('every');
    });

    it('reads ISO weekdays from calendar dates', () => {
        expect(isoWeekday('2026-11-02')).toBe(1);
        expect(isoWeekday('2026-11-08')).toBe(7);
    });

    it('picks the schedule made for that weekday over broader ones', () => {
        const schedules = [
            schedule(1, 'Every day', []),
            schedule(2, 'Workday', [1, 2, 3, 4, 5]),
            schedule(3, 'Tuesday', [2]),
            schedule(4, 'Saturday', [6]),
        ];

        expect(scheduleFor(schedules, '2026-11-03')?.name).toBe('Tuesday');
        expect(scheduleFor(schedules, '2026-11-04')?.name).toBe('Workday');
        expect(scheduleFor(schedules, '2026-11-08')?.name).toBe('Every day');
        expect(scheduleFor([schedule(4, 'Saturday', [6])], '2026-11-03')).toBeUndefined();
    });
});
