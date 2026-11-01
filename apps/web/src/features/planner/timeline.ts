import type { TimeBlock } from '@focus/types';

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
