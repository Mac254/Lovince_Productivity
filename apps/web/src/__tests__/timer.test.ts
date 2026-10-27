import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { timerPresets, useTimerStore } from '@/stores/timer';

vi.mock('@/lib/chime', () => ({ playChime: vi.fn() }));

const MINUTE = 60_000;

describe('focus timer', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-28T09:00:00'));
        localStorage.clear();
        setActivePinia(createPinia());
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('counts down, pauses without losing time, and resumes', () => {
        const timer = useTimerStore();
        timer.choose(timerPresets.find((p) => p.id === 'short')!);
        expect(timer.display).toBe('05:00');

        timer.start();
        vi.advanceTimersByTime(60_000);
        expect(timer.display).toBe('04:00');

        timer.pause();
        vi.advanceTimersByTime(10 * MINUTE);
        expect(timer.display).toBe('04:00');

        timer.start();
        vi.advanceTimersByTime(30_000);
        expect(timer.display).toBe('03:30');
        expect(timer.progress).toBeCloseTo(0.3, 2);
    });

    it('adds time to a running session', () => {
        const timer = useTimerStore();
        timer.setCustom(10);
        timer.start();
        timer.addMinutes(5);
        expect(timer.display).toBe('15:00');
    });

    it('finishes, counts focus sessions, and not breaks', () => {
        const timer = useTimerStore();
        timer.setCustom(1);
        timer.start();
        vi.advanceTimersByTime(MINUTE + 500);
        expect(timer.status).toBe('done');
        expect(timer.sessions.count).toBe(1);

        timer.choose(timerPresets.find((p) => p.kind === 'break')!);
        timer.start();
        vi.advanceTimersByTime(20 * MINUTE);
        expect(timer.sessions.count).toBe(1);
    });

    it('clamps custom lengths to 1–180 minutes', () => {
        const timer = useTimerStore();
        timer.setCustom(0);
        expect(timer.durationMs).toBe(MINUTE);
        timer.setCustom(999);
        expect(timer.durationMs).toBe(180 * MINUTE);
    });

    it('resumes a running timer after a reload, and completes one that ended while away', () => {
        const first = useTimerStore();
        first.setCustom(10);
        first.start();
        vi.advanceTimersByTime(2 * MINUTE);

        setActivePinia(createPinia());
        const reloaded = useTimerStore();
        expect(reloaded.status).toBe('running');
        expect(reloaded.display).toBe('08:00');

        vi.setSystemTime(new Date('2026-10-28T10:00:00'));
        setActivePinia(createPinia());
        const later = useTimerStore();
        expect(later.status).toBe('done');
        expect(later.sessions.count).toBe(1);
    });

    it('starts a short break when a focus session ends, if asked to', () => {
        const timer = useTimerStore();
        timer.autoBreak = true;
        timer.setCustom(1);
        timer.start();
        vi.advanceTimersByTime(MINUTE + 1000);

        expect(timer.kind).toBe('break');
        expect(timer.status).toBe('running');
        expect(timer.display).toBe('04:59');
    });
});
