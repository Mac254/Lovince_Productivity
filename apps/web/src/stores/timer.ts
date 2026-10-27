import { defineStore } from 'pinia';
import { computed, ref, watch } from 'vue';
import { playChime } from '@/lib/chime';
import { todayIso } from '@/lib/dates';

export type SessionKind = 'focus' | 'break';
export type TimerStatus = 'idle' | 'running' | 'paused' | 'done';

export interface TimerPreset {
    id: string;
    label: string;
    minutes: number;
    kind: SessionKind;
}

export const timerPresets: TimerPreset[] = [
    { id: 'focus', label: 'Focus', minutes: 25, kind: 'focus' },
    { id: 'deep', label: 'Deep work', minutes: 50, kind: 'focus' },
    { id: 'short', label: 'Break', minutes: 5, kind: 'break' },
    { id: 'long', label: 'Long break', minutes: 15, kind: 'break' },
];

export const MIN_MINUTES = 1;
export const MAX_MINUTES = 180;
const STORAGE_KEY = 'focus.timer';
const MINUTE = 60_000;

interface Persisted {
    presetId: string;
    kind: SessionKind;
    durationMs: number;
    endsAt: number | null;
    pausedRemainingMs: number | null;
    status: TimerStatus;
    task: { id: number; title: string } | null;
    sound: boolean;
    autoBreak: boolean;
    sessions: { date: string; count: number };
}

function read(): Partial<Persisted> {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') as Partial<Persisted>;
    } catch {
        return {};
    }
}

/**
 * A countdown measured against a stored end time, so it stays accurate in background tabs
 * and survives reloads. Per-device state: kept in localStorage, never sent to the server.
 */
export const useTimerStore = defineStore('timer', () => {
    const saved = read();

    const presetId = ref(saved.presetId ?? 'focus');
    const kind = ref<SessionKind>(saved.kind ?? 'focus');
    const durationMs = ref(saved.durationMs ?? 25 * MINUTE);
    const endsAt = ref<number | null>(saved.endsAt ?? null);
    const pausedRemainingMs = ref<number | null>(saved.pausedRemainingMs ?? null);
    const status = ref<TimerStatus>(saved.status ?? 'idle');
    const task = ref<Persisted['task']>(saved.task ?? null);
    const sound = ref(saved.sound ?? true);
    const autoBreak = ref(saved.autoBreak ?? false);
    const sessions = ref(saved.sessions?.date === todayIso() ? saved.sessions : { date: todayIso(), count: 0 });
    const now = ref(Date.now());
    const baseTitle = ref('Focus');
    let ticker: number | undefined;

    const remainingMs = computed(() => {
        if (status.value === 'running' && endsAt.value) return Math.max(0, endsAt.value - now.value);
        if (status.value === 'paused' && pausedRemainingMs.value !== null) return pausedRemainingMs.value;
        if (status.value === 'done') return 0;
        return durationMs.value;
    });
    const progress = computed(() => (durationMs.value ? 1 - remainingMs.value / durationMs.value : 0));
    const display = computed(() => {
        const total = Math.ceil(remainingMs.value / 1000);
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = String(total % 60).padStart(2, '0');
        return h ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${String(m).padStart(2, '0')}:${s}`;
    });
    const active = computed(() => status.value === 'running' || status.value === 'paused');

    function startTicker(): void {
        stopTicker();
        ticker = window.setInterval(() => {
            now.value = Date.now();
            if (status.value === 'running' && endsAt.value && now.value >= endsAt.value) finish(true);
        }, 250);
    }

    function stopTicker(): void {
        window.clearInterval(ticker);
        ticker = undefined;
    }

    function choose(preset: TimerPreset): void {
        presetId.value = preset.id;
        kind.value = preset.kind;
        setLength(preset.minutes);
    }

    function setCustom(minutes: number, sessionKind: SessionKind = 'focus'): void {
        presetId.value = 'custom';
        kind.value = sessionKind;
        setLength(minutes);
    }

    function setLength(minutes: number): void {
        const clamped = Math.min(MAX_MINUTES, Math.max(MIN_MINUTES, Math.round(minutes)));
        durationMs.value = clamped * MINUTE;
        reset();
    }

    function start(): void {
        const remaining =
            status.value === 'paused' && pausedRemainingMs.value !== null ? pausedRemainingMs.value : durationMs.value;
        now.value = Date.now();
        endsAt.value = now.value + remaining;
        pausedRemainingMs.value = null;
        status.value = 'running';
        startTicker();
    }

    function pause(): void {
        if (status.value !== 'running') return;
        pausedRemainingMs.value = remainingMs.value;
        endsAt.value = null;
        status.value = 'paused';
        stopTicker();
    }

    function toggle(): void {
        if (status.value === 'running') pause();
        else start();
    }

    function reset(): void {
        stopTicker();
        endsAt.value = null;
        pausedRemainingMs.value = null;
        status.value = 'idle';
    }

    function addMinutes(minutes: number): void {
        const extra = minutes * MINUTE;
        durationMs.value += extra;
        if (status.value === 'running' && endsAt.value) endsAt.value += extra;
        else if (status.value === 'paused' && pausedRemainingMs.value !== null) pausedRemainingMs.value += extra;
        else if (status.value === 'done') {
            durationMs.value = extra;
            start();
        }
    }

    function finish(announce: boolean): void {
        stopTicker();
        endsAt.value = null;
        pausedRemainingMs.value = null;
        status.value = 'done';
        if (kind.value === 'focus') {
            if (sessions.value.date !== todayIso()) sessions.value = { date: todayIso(), count: 0 };
            sessions.value = { ...sessions.value, count: sessions.value.count + 1 };
        }
        if (!announce) return;
        if (sound.value) playChime();
        notify();
        if (autoBreak.value && kind.value === 'focus') {
            choose(timerPresets.find((preset) => preset.id === 'short')!);
            start();
        }
    }

    function notify(): void {
        try {
            if (!('Notification' in window) || Notification.permission !== 'granted' || !document.hidden) return;
            new Notification(kind.value === 'focus' ? 'Focus session complete' : 'Break is over', {
                body:
                    kind.value === 'focus'
                        ? task.value
                            ? `Nice work on “${task.value.title}”.`
                            : 'Time for a break.'
                        : 'Ready for the next session?',
                silent: true,
            });
        } catch {
            // Notifications are optional.
        }
    }

    function setTask(value: Persisted['task']): void {
        task.value = value;
    }

    // Resume after a reload: a run that ended while away counts, quietly.
    if (status.value === 'running') {
        if (endsAt.value && endsAt.value <= Date.now()) finish(false);
        else startTicker();
    }

    watch(
        [presetId, kind, durationMs, endsAt, pausedRemainingMs, status, task, sound, autoBreak, sessions],
        () => {
            try {
                const state: Persisted = {
                    presetId: presetId.value,
                    kind: kind.value,
                    durationMs: durationMs.value,
                    endsAt: endsAt.value,
                    pausedRemainingMs: pausedRemainingMs.value,
                    status: status.value,
                    task: task.value,
                    sound: sound.value,
                    autoBreak: autoBreak.value,
                    sessions: sessions.value,
                };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
            } catch {
                // Without storage the timer still works for this page view.
            }
        },
        // Saved synchronously so a reload right after Start never loses the running timer.
        { deep: true, flush: 'sync' },
    );

    // The tab title shows the countdown while a timer runs.
    watch([display, status, baseTitle], () => {
        if (typeof document === 'undefined') return;
        document.title =
            status.value === 'running'
                ? `${display.value} · ${kind.value === 'focus' ? 'Focus' : 'Break'}`
                : baseTitle.value;
    });

    return {
        presetId,
        kind,
        durationMs,
        status,
        task,
        sound,
        autoBreak,
        sessions,
        baseTitle,
        remainingMs,
        progress,
        display,
        active,
        choose,
        setCustom,
        start,
        pause,
        toggle,
        reset,
        addMinutes,
        setTask,
        finish,
    };
});
