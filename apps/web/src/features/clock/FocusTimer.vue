<script setup lang="ts">
import { api } from '@focus/api-client';
import type { Task } from '@focus/types';
import { Button, Icon, IconButton, useToast } from '@focus/ui';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useTaskActions } from '@/features/tasks/useTaskActions';
import { useDashboardStore } from '@/stores/dashboard';
import { MAX_MINUTES, MIN_MINUTES, timerPresets, useTimerStore } from '@/stores/timer';
import RollingDigits from './RollingDigits.vue';
import TimerRing from './TimerRing.vue';

const timer = useTimerStore();
const dashboard = useDashboardStore();
const toast = useToast();
const tasks = ref<Task[]>([]);
const customMinutes = ref(Math.round(timer.durationMs / 60_000));
const ringSize = ref(320);
const notificationsAllowed = ref(typeof Notification !== 'undefined' && Notification.permission === 'granted');
const canNotify = typeof Notification !== 'undefined' && Notification.permission !== 'denied';

const actions = useTaskActions(() => void dashboard.load());

const statusLine = computed(() => {
    if (timer.status === 'done') return timer.kind === 'focus' ? 'Session complete' : 'Break over';
    if (timer.status === 'paused') return 'Paused';
    if (timer.status === 'running') return timer.kind === 'focus' ? 'Focusing' : 'On a break';
    return timer.kind === 'focus' ? 'Ready to focus' : 'Ready for a break';
});

const taskId = computed({
    get: () => timer.task?.id ?? '',
    set: (id: number | '') => {
        const found = tasks.value.find((t) => t.id === id);
        timer.setTask(found ? { id: found.id, title: found.title } : null);
    },
});

function applyCustom(): void {
    const minutes = Number(customMinutes.value);
    if (!Number.isFinite(minutes)) return;
    timer.setCustom(minutes, timer.kind);
    customMinutes.value = Math.round(timer.durationMs / 60_000);
}

async function enableNotifications(): Promise<void> {
    try {
        notificationsAllowed.value = (await Notification.requestPermission()) === 'granted';
        if (!notificationsAllowed.value) toast.info('Notifications stay off. The chime still plays.');
    } catch {
        toast.error('This browser can’t show notifications.');
    }
}

async function completeTask(): Promise<void> {
    const current = tasks.value.find((t) => t.id === timer.task?.id);
    if (!current) return;
    await actions.setStatus(current, 'completed');
    timer.setTask(null);
    await loadTasks();
}

function startBreak(): void {
    const preset = timerPresets.find((p) => p.id === 'short')!;
    timer.choose(preset);
    timer.start();
}

async function loadTasks(): Promise<void> {
    tasks.value = (await api.tasks.list({ status: 'open', per_page: 100 }).catch(() => ({ data: [] as Task[] }))).data;
}

function onKey(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;
    if (target && ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(target.tagName)) return;
    if (event.code === 'Space') {
        event.preventDefault();
        timer.toggle();
    } else if (event.key.toLowerCase() === 'r' && !event.ctrlKey && !event.metaKey) {
        timer.reset();
    }
}

function resize(): void {
    ringSize.value = Math.max(
        220,
        Math.min(380, Math.floor(Math.min(window.innerWidth - 48, window.innerHeight * 0.5))),
    );
}

watch(
    () => timer.durationMs,
    (ms) => {
        if (timer.status === 'idle') customMinutes.value = Math.round(ms / 60_000);
    },
);

onMounted(() => {
    void loadTasks();
    resize();
    window.addEventListener('resize', resize);
    document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    window.removeEventListener('resize', resize);
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <section aria-labelledby="timer-heading" class="flex w-full max-w-dialog flex-col items-center gap-6">
        <h2 id="timer-heading" class="sr-only">Focus timer</h2>

        <div
            role="group"
            aria-label="Session length"
            class="flex flex-wrap justify-center gap-2 rounded-full bg-chrome p-1 shadow-floating"
        >
            <button
                v-for="preset in timerPresets"
                :key="preset.id"
                type="button"
                :aria-pressed="timer.presetId === preset.id"
                :disabled="timer.active"
                :class="[
                    'h-(--control-sm) rounded-full px-4 text-label font-semibold whitespace-nowrap transition-colors disabled:cursor-not-allowed',
                    timer.presetId === preset.id
                        ? 'bg-highlight text-highlight-ink'
                        : 'text-chrome-fg hover:bg-chrome-hover disabled:opacity-50',
                ]"
                @click="timer.choose(preset)"
            >
                {{ preset.label }} {{ preset.minutes }}m
            </button>
        </div>

        <TimerRing :progress="timer.progress" :size="ringSize" :label="`${statusLine}, ${timer.display} remaining`">
            <p class="text-label font-semibold tracking-wide uppercase opacity-80">{{ statusLine }}</p>
            <p class="timer-digits font-display font-semibold" aria-hidden="true">
                <RollingDigits :text="timer.display" />
            </p>
            <p v-if="timer.task" class="mt-1 max-w-56 truncate text-label opacity-90">{{ timer.task.title }}</p>
        </TimerRing>
        <p class="sr-only" aria-live="assertive">{{ timer.status === 'done' ? statusLine : '' }}</p>

        <div class="flex flex-wrap items-center justify-center gap-3">
            <IconButton
                icon="retry"
                label="Reset timer (R)"
                variant="secondary"
                size="lg"
                motion="spin"
                :disabled="timer.status === 'idle'"
                @click="timer.reset()"
            />
            <button
                type="button"
                class="group inline-flex h-(--control-lg) min-w-40 items-center justify-center gap-2 rounded-full bg-highlight px-8 text-lead font-semibold text-highlight-ink shadow-floating transition-colors hover:bg-highlight-hover"
                @click="timer.status === 'done' ? (timer.reset(), timer.start()) : timer.toggle()"
            >
                <Icon :name="timer.status === 'running' ? 'clock' : 'arrow-right'" size="md" motion="pop" />
                {{
                    timer.status === 'running'
                        ? 'Pause'
                        : timer.status === 'paused'
                          ? 'Resume'
                          : timer.status === 'done'
                            ? 'Again'
                            : 'Start'
                }}
            </button>
            <button
                type="button"
                class="h-(--control-lg) rounded-full bg-chrome px-6 text-label font-semibold text-chrome-fg transition-colors hover:bg-chrome-hover"
                @click="timer.addMinutes(5)"
            >
                +5 min
            </button>
        </div>

        <div
            v-if="timer.status === 'done'"
            class="flex flex-wrap justify-center gap-2 rounded-lg bg-chrome p-4 text-chrome-fg shadow-floating"
        >
            <Button v-if="timer.kind === 'focus'" size="sm" icon-start="sun" @click="startBreak"
                >Take a 5-min break</Button
            >
            <Button
                v-if="timer.kind === 'focus' && timer.task"
                size="sm"
                variant="secondary"
                icon-start="check"
                @click="completeTask"
            >
                Mark “{{ timer.task.title }}” complete
            </Button>
        </div>

        <div class="grid w-full gap-3 rounded-lg bg-chrome p-4 text-chrome-fg shadow-floating sm:grid-cols-2">
            <label class="flex flex-col gap-1 text-label font-semibold">
                Focusing on
                <select
                    v-model="taskId"
                    class="h-(--control-md) rounded-md border border-chrome-border bg-chrome-hover px-3 text-body text-chrome-fg focus-visible:border-highlight focus-visible:outline-none"
                >
                    <option value="">No task — just focus</option>
                    <option v-for="item in tasks" :key="item.id" :value="item.id">{{ item.title }}</option>
                </select>
            </label>
            <form class="flex flex-col gap-1" @submit.prevent="applyCustom">
                <label for="custom-minutes" class="text-label font-semibold">Custom length (minutes)</label>
                <div class="flex gap-2">
                    <input
                        id="custom-minutes"
                        v-model.number="customMinutes"
                        type="number"
                        inputmode="numeric"
                        :min="MIN_MINUTES"
                        :max="MAX_MINUTES"
                        :disabled="timer.active"
                        class="h-(--control-md) w-full rounded-md border border-chrome-border bg-chrome-hover px-3 text-body text-chrome-fg tabular focus-visible:border-highlight focus-visible:outline-none disabled:opacity-50"
                    />
                    <button
                        type="submit"
                        :disabled="timer.active"
                        class="h-(--control-md) rounded-md bg-highlight px-4 text-label font-semibold text-highlight-ink disabled:opacity-50"
                    >
                        Set
                    </button>
                </div>
            </form>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-label sm:col-span-2">
                <span class="flex items-center gap-2 font-semibold text-highlight">
                    <Icon name="flame" />
                    {{ timer.sessions.count }} {{ timer.sessions.count === 1 ? 'session' : 'sessions' }} today
                </span>
                <label class="flex cursor-pointer items-center gap-2">
                    <input v-model="timer.sound" type="checkbox" class="size-(--icon-sm) accent-highlight" />
                    Chime
                </label>
                <button
                    v-if="canNotify && !notificationsAllowed"
                    type="button"
                    class="font-semibold text-chrome-fg-muted underline underline-offset-4 hover:text-chrome-fg"
                    @click="enableNotifications"
                >
                    Notify me when time’s up
                </button>
                <span class="ml-auto text-chrome-fg-muted">Space start/pause · R reset · F full screen</span>
            </div>
        </div>
    </section>
</template>

<style scoped>
.timer-digits {
    font-size: clamp(3rem, 9vw, 5.5rem);
    line-height: 1;
}
</style>
