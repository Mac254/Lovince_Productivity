import type { TimeBlock } from '@focus/types';
import { parseDate, todayIso } from '@/lib/dates';

export const DAY_MINUTES = 1440;

export function clockTime(minute: number): string {
    const value = ((minute % DAY_MINUTES) + DAY_MINUTES) % DAY_MINUTES;
    return `${String(Math.floor(value / 60)).padStart(2, '0')}:${String(value % 60).padStart(2, '0')}`;
}

export function parseClockTime(text: string): number | null {
    const match = /^(\d{1,2}):(\d{2})$/.exec(text.trim());
    if (!match) return null;
    const hours = Number(match[1]);
    const minutes = Number(match[2]);
    return hours < 24 && minutes < 60 ? hours * 60 + minutes : null;
}

export function snap(minute: number, step: number): number {
    return Math.min(DAY_MINUTES - step, Math.max(0, Math.floor(minute / step) * step));
}

export function visibleRange(dayStart: number, dayEnd: number, blocks: TimeBlock[]): { start: number; end: number } {
    const start = Math.min(dayStart * 60, ...blocks.map((block) => Math.floor(block.start_minute / 60) * 60));
    const end = Math.max(
        dayEnd * 60,
        ...blocks.map((block) => Math.ceil((block.start_minute + block.minutes) / 60) * 60),
    );
    return { start, end: Math.min(DAY_MINUTES, end) };
}

export function freeMinutes(blocks: TimeBlock[], start: number, step: number): number {
    const next = blocks
        .map((block) => block.start_minute)
        .filter((value) => value >= start + step)
        .sort((a, b) => a - b)[0];
    return Math.min(60, (next ?? DAY_MINUTES) - start);
}

export function plannedMinutes(blocks: TimeBlock[]): number {
    return blocks.reduce((total, block) => total + block.minutes, 0);
}

export function missedBlocks(blocks: TimeBlock[]): TimeBlock[] {
    return blocks.filter(
        (block) => block.task && block.task.status !== 'completed' && block.task.status !== 'cancelled',
    );
}

export function previousDay(date: string): string {
    const day = parseDate(date);
    day.setDate(day.getDate() - 1);
    return todayIso(day);
}

export function freeSlot(blocks: TimeBlock[], minutes: number, preferred: number): number | null {
    const ends = [...new Set([0, ...blocks.map((block) => block.start_minute + block.minutes)])].sort((a, b) => a - b);
    const candidates = [preferred, ...ends.filter((end) => end > preferred), ...ends.filter((end) => end < preferred)];
    return (
        candidates.find(
            (start) =>
                start + minutes <= DAY_MINUTES &&
                blocks.every(
                    (block) => block.start_minute >= start + minutes || block.start_minute + block.minutes <= start,
                ),
        ) ?? null
    );
}

const countBlocks = (count: number) => `${count} ${count === 1 ? 'block' : 'blocks'}`;

export function missedSummary(count: number, stuck: boolean): string {
    if (stuck) return `${countBlocks(count)} from yesterday ${count === 1 ? 'doesn’t' : 'don’t'} fit in today’s plan`;
    return `${countBlocks(count)} from yesterday ${count === 1 ? 'wasn’t' : 'weren’t'} finished`;
}

export function movedSummary({ moved, skipped }: { moved: number; skipped: number }): string {
    const head = moved ? `Moved ${countBlocks(moved)} to today` : 'Nothing moved';
    return skipped
        ? `${head}. ${countBlocks(skipped)} didn’t fit anywhere in today’s plan and stayed on yesterday.`
        : head;
}
