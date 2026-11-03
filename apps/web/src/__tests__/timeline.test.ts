import type { TimeBlock } from '@focus/types';
import { describe, expect, it } from 'vitest';
import {
    clockTime,
    freeMinutes,
    freeSlot,
    missedBlocks,
    missedSummary,
    movedSummary,
    parseClockTime,
    previousDay,
    snap,
    visibleRange,
} from '@/features/planner/timeline';

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

    it('finds blocks for tasks left unfinished', () => {
        const withTask = (id: number, status: NonNullable<TimeBlock['task']>['status']): TimeBlock => ({
            ...block(id, 30),
            task: { id, title: 'Task', status, priority: 'medium' },
        });
        const blocks = [
            block(0, 30),
            withTask(60, 'in_progress'),
            withTask(120, 'completed'),
            withTask(180, 'cancelled'),
        ];

        expect(missedBlocks(blocks).map((entry) => entry.id)).toEqual([60]);
    });

    it('steps back a calendar day and summarises a move', () => {
        expect(previousDay('2026-11-01')).toBe('2026-10-31');
        expect(previousDay('2026-03-01')).toBe('2026-02-28');
        expect(movedSummary({ moved: 1, skipped: 0 })).toBe('Moved 1 block to today');
        expect(movedSummary({ moved: 2, skipped: 1 })).toBe(
            'Moved 2 blocks to today. 1 block didn’t fit anywhere in today’s plan and stayed on yesterday.',
        );
        expect(movedSummary({ moved: 0, skipped: 3 })).toBe(
            'Nothing moved. 3 blocks didn’t fit anywhere in today’s plan and stayed on yesterday.',
        );
    });

    it('pluralises the missed blocks banner', () => {
        expect(missedSummary(1, false)).toBe('1 block from yesterday wasn’t finished');
        expect(missedSummary(2, false)).toBe('2 blocks from yesterday weren’t finished');
        expect(missedSummary(1, true)).toBe('1 block from yesterday doesn’t fit in today’s plan');
        expect(missedSummary(3, true)).toBe('3 blocks from yesterday don’t fit in today’s plan');
    });

    it('keeps a carried block at its time when that time is free', () => {
        expect(freeSlot([block(540, 60)], 30, 600)).toBe(600);
        expect(freeSlot([], 30, 600)).toBe(600);
    });

    it('moves a carried block that overlaps today’s plan to the next free slot', () => {
        const booked = [block(300, 270), block(570, 450)];
        expect(freeSlot(booked, 60, 600)).toBe(1020);
        expect(freeSlot([block(540, 60), block(630, 60)], 30, 560)).toBe(600);
        expect(freeSlot([block(0, 1380)], 60, 600)).toBe(1380);
    });

    it('looks earlier in the day when nothing later is free', () => {
        expect(freeSlot([block(600, 840)], 60, 900)).toBe(0);
    });

    it('finds no slot when the block fits nowhere today', () => {
        expect(freeSlot([block(0, 720), block(720, 720)], 30, 600)).toBeNull();
        expect(freeSlot([block(0, 1200), block(1230, 210)], 60, 600)).toBeNull();
    });
});
