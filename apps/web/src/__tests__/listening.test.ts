import { describe, expect, it } from 'vitest';
import type { BookReading } from '@focus/types';
import { listeningWeeks } from '@/features/books/listening';

const reading = (read_on: string, minutes: number) => ({ read_on, minutes }) as BookReading;

describe('listeningWeeks', () => {
    it('adds each episode to the week it was heard in', () => {
        const weeks = listeningWeeks(
            [reading('2026-11-24', 30), reading('2026-11-23', 15), reading('2026-11-20', 45), reading('2026-01-01', 9)],
            3,
            '2026-11-24',
        );
        expect(weeks.map((w) => w.start)).toEqual(['2026-11-09', '2026-11-16', '2026-11-23']);
        expect(weeks.map((w) => w.minutes)).toEqual([0, 45, 45]);
        expect(weeks[2]?.episodes).toBe(2);
    });
});
