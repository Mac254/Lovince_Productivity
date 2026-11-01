import { describe, expect, it } from 'vitest';
import { weekStartIso } from '@/features/reviews/week';

describe('weekStartIso', () => {
    it('finds the Monday of a week', () => {
        expect(weekStartIso('2026-11-06')).toBe('2026-11-02');
        expect(weekStartIso('2026-11-08')).toBe('2026-11-02');
        expect(weekStartIso('2026-11-02')).toBe('2026-11-02');
        expect(weekStartIso('2026-11-01')).toBe('2026-10-26');
    });
});
