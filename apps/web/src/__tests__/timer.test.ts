import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '@/stores/auth';
import { timerPresets, useTimerStore } from '@/stores/timer';

vi.mock('@/lib/chime', () => ({ playChime: vi.fn() }));

const { create, list, getTimer, saveTimer } = vi.hoisted(() => ({
    getTimer: vi.fn(),
    saveTimer: vi.fn(),
    create: vi.fn().mockResolvedValue({}),
    list: vi.fn().mockResolvedValue({ data: [], meta: { minutes: 1, count: 1, goal_minutes: 120, streak: 1 } }),
}));

vi.mock('@focus/api-client', async (original) => {
    const actual = await original<typeof import('@focus/api-client')>();
    return {
        ...actual,
        api: {
            ...actual.api,
            focusSessions: { create, list, remove: vi.fn() },
            focusTimer: { get: getTimer, save: saveTimer },
        },
    };
});

const MINUTE = 60_000;

describe('focus timer', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-28T09:00:00'));
        localStorage.clear();
        create.mockClear();
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

    it('records finished focus sessions, and not breaks', () => {
        const timer = useTimerStore();
        timer.setCustom(1);
        timer.start();
        vi.advanceTimersByTime(MINUTE + 500);
        expect(timer.status).toBe('done');
        expect(create).toHaveBeenCalledTimes(1);
        expect(create).toHaveBeenCalledWith(
            expect.objectContaining({
                planned_minutes: 1,
                focused_seconds: 60,
                completed: true,
                started_on: '2026-10-28',
            }),
        );

        timer.choose(timerPresets.find((p) => p.kind === 'break')!);
        timer.start();
        vi.advanceTimersByTime(20 * MINUTE);
        expect(create).toHaveBeenCalledTimes(1);
    });

    it('records a session stopped early after a minute, but not a shorter one', () => {
        const timer = useTimerStore();
        timer.setCustom(25);
        timer.start();
        vi.advanceTimersByTime(30_000);
        timer.reset();
        expect(create).not.toHaveBeenCalled();

        timer.start();
        vi.advanceTimersByTime(10 * MINUTE);
        timer.pause();
        vi.advanceTimersByTime(5 * MINUTE);
        timer.start();
        vi.advanceTimersByTime(2 * MINUTE);
        timer.reset();
        expect(create).toHaveBeenCalledWith(expect.objectContaining({ focused_seconds: 720, completed: false }));
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
        expect(create).toHaveBeenCalledWith(expect.objectContaining({ focused_seconds: 600, completed: true }));
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

    it('follows the timer from another device and shares its own changes', async () => {
        getTimer.mockResolvedValue({
            revision: 3,
            server_time: Date.now(),
            state: {
                preset_id: 'deep',
                kind: 'focus',
                status: 'running',
                duration_ms: 50 * MINUTE,
                ends_at: Date.now() + 40 * MINUTE,
                paused_remaining_ms: null,
                started_at: Date.now() - 10 * MINUTE,
                task: null,
            },
        });
        saveTimer.mockResolvedValue({ revision: 4, server_time: Date.now(), state: null });
        useAuthStore().user = { id: 1 } as never;

        const timer = useTimerStore();
        await vi.waitFor(() => expect(timer.status).toBe('running'));
        expect(timer.display).toBe('40:00');
        expect(saveTimer).not.toHaveBeenCalled();

        timer.pause();
        await vi.advanceTimersByTimeAsync(400);
        expect(saveTimer).toHaveBeenCalledWith(
            3,
            expect.objectContaining({ status: 'paused', ends_at: null, started_at: expect.any(Number) }),
        );
    });
});
