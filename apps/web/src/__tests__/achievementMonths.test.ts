import type { Achievement } from '@focus/types';
import { describe, expect, it } from 'vitest';
import { groupByDate } from '@/features/achievements/months';

const achievement = (id: number, achieved_on: string) => ({ id, achieved_on }) as Achievement;

const sample = [
    achievement(1, '2026-10-02'),
    achievement(2, '2026-11-02'),
    achievement(3, '2026-10-28'),
    achievement(4, '2025-11-30'),
    achievement(5, '2026-10-28'),
];

describe('groupByDate', () => {
    it('groups by year, month and day, newest first', () => {
        const years = groupByDate(sample, 'newest');

        expect(years.map((y) => [y.year, y.count])).toEqual([
            ['2026', 4],
            ['2025', 1],
        ]);
        expect(years[0]!.months.map((m) => m.key)).toEqual(['2026-11', '2026-10']);
        const october = years[0]!.months[1]!;
        expect(october.count).toBe(3);
        expect(october.days.map((d) => [d.date, d.items.map((a) => a.id)])).toEqual([
            ['2026-10-28', [5, 3]],
            ['2026-10-02', [1]],
        ]);
    });

    it('lists the oldest first by default', () => {
        const years = groupByDate(sample);

        expect(years.map((y) => y.year)).toEqual(['2025', '2026']);
        expect(years[1]!.months[0]!.days.map((d) => d.date)).toEqual(['2026-10-02', '2026-10-28']);
        expect(years[1]!.months[0]!.days[1]!.items.map((a) => a.id)).toEqual([3, 5]);
    });
});
