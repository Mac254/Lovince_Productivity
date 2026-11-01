import type { TimeBlock } from '@focus/types';
import { describe, expect, it } from 'vitest';
import { clockTime, freeMinutes, parseClockTime, snap, visibleRange } from '@/features/planner/timeline';

const block = (start_minute: number, minutes: number): TimeBlock => ({
    id: start_minute,
    date: '2026-11-02',
    start_minute,
    minutes,
    title: 'Block',
    task: null,
});

describe('timeline helpers', () => {
    it('formats and parses clock times', () => {
        expect(clockTime(545)).toBe('09:05');
        expect(clockTime(0)).toBe('00:00');
        expect(parseClockTime('9:30')).toBe(570);
        expect(parseClockTime('24:00')).toBeNull();
        expect(parseClockTime('nope')).toBeNull();
    });

    it('snaps to the step and keeps the block inside the day', () => {
        expect(snap(547, 15)).toBe(540);
        expect(snap(1439, 30)).toBe(1410);
        expect(snap(-10, 30)).toBe(0);
    });

    it('widens the range to fit blocks outside the working day', () => {
        expect(visibleRange(8, 18, [])).toEqual({ start: 480, end: 1080 });
        expect(visibleRange(8, 18, [block(390, 30), block(1170, 45)])).toEqual({ start: 360, end: 1260 });
    });

    it('fits a new block before the next one', () => {
        expect(freeMinutes([block(600, 60)], 570, 30)).toBe(30);
        expect(freeMinutes([], 570, 30)).toBe(60);
    });
});
