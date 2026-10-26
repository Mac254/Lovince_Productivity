import { describe, expect, it } from 'vitest';
import { daysUntil, dueLabel, todayIso } from '@/lib/dates';

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
});
