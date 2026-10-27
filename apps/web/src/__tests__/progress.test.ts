import type { DashboardStats } from '@focus/types';
import { describe, expect, it } from 'vitest';
import { dayProgress, dayProgressLabel } from '@/lib/progress';

const stats = (planned_completed_today: number, planned_today: number, planned_worked_today = 0) =>
    ({ planned_completed_today, planned_today, planned_worked_today }) as DashboardStats;

describe('dayProgress', () => {
    it('counts completed planned tasks fully', () => {
        expect(dayProgress(stats(1, 3))).toMatchObject({ done: 1, total: 4, remaining: 3, percent: 25 });
    });

    it('counts open planned tasks with work logged today as half done', () => {
        const day = dayProgress(stats(1, 3, 2));
        expect(day.percent).toBe(50);
        expect(dayProgressLabel(day)).toBe('1 of 4 done · 2 worked on');
    });

    it('is zero with nothing planned', () => {
        expect(dayProgress(stats(0, 0))).toMatchObject({ total: 0, percent: 0 });
        expect(dayProgressLabel(dayProgress(stats(0, 2)))).toBe('0 of 2 done');
    });
});
