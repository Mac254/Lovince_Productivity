import { describe, expect, it } from 'vitest';
import { addMonths, monthGrid, shiftDate } from '@/lib/calendar';

describe('calendar', () => {
    it('builds Monday-first weeks that cover the whole month', () => {
        const weeks = monthGrid('2026-10');
        expect(weeks).toHaveLength(5);
        expect(weeks[0]![0]).toEqual({ date: '2026-09-28', day: 28, inMonth: false });
        expect(weeks[0]![3]).toEqual({ date: '2026-10-01', day: 1, inMonth: true });
        expect(weeks.at(-1)!.at(-1)!.date).toBe('2026-11-01');
        expect(weeks.flat().filter((d) => d.inMonth)).toHaveLength(31);
    });

    it('starts on the 1st when the month begins on a Monday and spans six weeks when needed', () => {
        expect(monthGrid('2026-06')[0]![0]!.date).toBe('2026-06-01');
        expect(monthGrid('2026-08')).toHaveLength(6);
    });

    it('moves across months and years', () => {
        expect(addMonths('2026-12', 1)).toBe('2027-01');
        expect(addMonths('2026-01', -1)).toBe('2025-12');
        expect(shiftDate('2026-10-31', 1)).toBe('2026-11-01');
    });
});
