import { describe, expect, it } from 'vitest';
import { daysUntil, dueLabel, formatMinutes, isoWeek, todayIso } from '@/lib/dates';

describe('dates', () => {
    const today = '2026-10-28';

    it('formats local dates without timezone drift', () => {
        expect(todayIso(new Date(2026, 0, 5, 23, 59))).toBe('2026-01-05');
    });

    it('counts calendar days', () => {
        expect(daysUntil('2026-11-04', today)).toBe(7);
        expect(daysUntil('2026-10-25', today)).toBe(-3);
    });

    it('describes due dates', () => {
        expect(dueLabel(null, today)).toBe('No due date');
        expect(dueLabel('2026-10-28', today)).toBe('Due today');
        expect(dueLabel('2026-10-29', today)).toBe('Due tomorrow');
        expect(dueLabel('2026-10-25', today)).toBe('3 days overdue');
        expect(dueLabel('2026-10-31', today)).toBe('Due in 3 days');
    });

    it('formats logged minutes as hours and minutes', () => {
        expect(formatMinutes(95)).toBe('1h 35m');
        expect(formatMinutes(120)).toBe('2h');
        expect(formatMinutes(45)).toBe('45m');
        expect(formatMinutes(null)).toBe('');
    });

    it('numbers weeks the ISO way', () => {
        expect(isoWeek(new Date(2026, 9, 27))).toBe(44);
        expect(isoWeek(new Date(2027, 0, 1))).toBe(53);
        expect(isoWeek(new Date(2025, 11, 29))).toBe(1);
    });
});
