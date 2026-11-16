import { describe, expect, it } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { periodLabel, periodStart, shiftPeriod, weekStartIso } from '@/features/reviews/period';
import { reflectionBlocks } from '@/features/reviews/reflection';

describe('weekStartIso', () => {
    it('finds the Monday of a week', () => {
        expect(weekStartIso('2026-11-06')).toBe('2026-11-02');
        expect(weekStartIso('2026-11-08')).toBe('2026-11-02');
        expect(weekStartIso('2026-11-02')).toBe('2026-11-02');
        expect(weekStartIso('2026-11-01')).toBe('2026-10-26');
    });
});

describe('review periods', () => {
    it('finds the start of the week, month and year', () => {
        expect(periodStart('week', '2026-11-16')).toBe('2026-11-16');
        expect(periodStart('month', '2026-11-16')).toBe('2026-11-01');
        expect(periodStart('year', '2026-11-16')).toBe('2026-01-01');
    });

    it('steps between periods', () => {
        expect(shiftPeriod('week', '2026-11-09', 1)).toBe('2026-11-16');
        expect(shiftPeriod('month', '2026-01-01', -1)).toBe('2025-12-01');
        expect(shiftPeriod('month', '2026-11-01', 2)).toBe('2027-01-01');
        expect(shiftPeriod('year', '2026-01-01', -1)).toBe('2025-01-01');
    });

    it('names a year by its number', () => {
        expect(periodLabel('year', '2026-01-01', '2026-12-31')).toBe('2026');
    });
});

describe('reflectionBlocks', () => {
    it('reads dashes and numbers as lists and joins wrapped lines', () => {
        expect(reflectionBlocks('A good week\nwith rest.\n\n- Shipped\n- Slept\n1. Rest\n2) Read')).toEqual([
            { kind: 'paragraph', text: 'A good week with rest.' },
            { kind: 'bullets', items: ['Shipped', 'Slept'] },
            { kind: 'numbered', items: ['Rest', 'Read'] },
        ]);
    });

    it('has nothing to show for an empty reflection', () => {
        expect(reflectionBlocks(null)).toEqual([]);
        expect(reflectionBlocks('  \n ')).toEqual([]);
    });
});

describe('review addresses', () => {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/review/:period(month|year)?/:start(\\d{4}-\\d{2}-\\d{2})?', name: 'review', component: {} }],
    });

    it('keeps old week links and adds months and years', () => {
        expect(router.resolve('/review/2026-11-09').params).toEqual({ start: '2026-11-09' });
        expect(router.resolve('/review/month/2026-11-01').params).toEqual({ period: 'month', start: '2026-11-01' });
        expect(router.resolve('/review/year').params).toEqual({ period: 'year' });
        expect(router.resolve({ name: 'review', params: { period: 'month', start: '2026-10-01' } }).path).toBe(
            '/review/month/2026-10-01',
        );
    });
});
