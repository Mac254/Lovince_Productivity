import type { Achievement } from '@focus/types';
import { describe, expect, it } from 'vitest';
import { groupByMonth } from '@/features/achievements/months';

const achievement = (id: number, achieved_on: string) => ({ id, achieved_on }) as Achievement;

describe('groupByMonth', () => {
    it('groups achievements by month, newest month first', () => {
        const months = groupByMonth([
            achievement(1, '2026-10-02'),
            achievement(2, '2026-11-02'),
            achievement(3, '2026-10-28'),
            achievement(4, '2025-11-30'),
        ]);

        expect(months.map((m) => m.key)).toEqual(['2026-11', '2026-10', '2025-11']);
        expect(months[1]!.items.map((a) => a.id)).toEqual([1, 3]);
    });
});
