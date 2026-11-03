<script setup lang="ts">
import { api } from '@focus/api-client';
import type { Task } from '@focus/types';
import { useTaskActions } from '@/features/tasks/useTaskActions';
import { formatMinutes } from '@/lib/dates';

const timer = useTimerStore();
const dashboard = useDashboardStore();
const toast = useToast();
const tasks = ref<Task[]>([]);
const customMinutes = ref(Math.round(timer.durationMs / 60_000));
const ringSize = ref(320);
const notificationsAllowed = ref(typeof Notification !== 'undefined' && Notification.permission === 'granted');
const canNotify = typeof Notification !== 'undefined' && Notification.permission !== 'denied';

const actions = useTaskActions(() => void dashboard.invalidate());

const todayLine = computed(() => {
    const today = timer.today;
    if (!today) return 'Focus time today';
    const time = formatMinutes(today.minutes) || '0m';
    const goal = today.goal_minutes ? ` of ${formatMinutes(today.goal_minutes)}` : '';
    return `${time}${goal} today`;
});

const statusLine = computed(() => {
    if (timer.status === 'done') return timer.kind === 'focus' ? 'Session complete' : 'Break over';
    if (timer.status === 'paused') return 'Paused';
    if (timer.status === 'running') return timer.kind === 'focus' ? 'Focusing' : 'On a break';
    return timer.kind === 'focus' ? 'Ready to focus' : 'Ready for a break';
});

const taskOptions = computed(() => [
    { value: '' as const, label: 'No task — just focus' },
    ...tasks.value.map((item) => ({ value: item.id, label: item.title })),
]);
/** The minutes field edits text; `applyCustom` reads it as a number. */
const customText = computed({
    get: () => String(customMinutes.value),
    set: (value: string | null) => (customMinutes.value = Number(value)),
});

const taskId = computed({
    get: () => timer.task?.id ?? '',
    set: (id: number | '' | null) => {
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
    void timer.flush().then(() => timer.loadToday());
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
            <Pressable
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
            </Pressable>
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
            <Pressable
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
            </Pressable>
            <Pressable
                type="button"
                class="h-(--control-lg) rounded-full bg-chrome px-6 text-label font-semibold text-chrome-fg transition-colors hover:bg-chrome-hover"
                @click="timer.addMinutes(5)"
            >
                +5 min
            </Pressable>
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
            <Combobox id="timer-task" v-model="taskId" label="Focusing on" :options="taskOptions" surface="chrome" />
            <form class="flex items-end gap-2" @submit.prevent="applyCustom">
                <div class="min-w-0 flex-1">
                    <Input
                        id="custom-minutes"
                        v-model="customText"
                        label="Custom length (minutes)"
                        type="number"
                        inputmode="numeric"
                        :min="MIN_MINUTES"
                        :max="MAX_MINUTES"
                        :disabled="timer.active"
                        surface="chrome"
                        class="tabular"
                    />
                </div>
                <Pressable
                    type="submit"
                    :disabled="timer.active"
                    class="h-(--control-md) rounded-md bg-highlight px-4 text-label font-semibold text-highlight-ink disabled:opacity-50"
                >
                    Set
                </Pressable>
            </form>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-label sm:col-span-2">
                <span class="flex items-center gap-2 font-semibold text-highlight">
                    <Icon name="flame" />
                    {{ todayLine }}
                </span>
                <Checkbox id="timer-chime" v-model="timer.sound" label="Chime" surface="chrome" />
                <Pressable
                    v-if="canNotify && !notificationsAllowed"
                    type="button"
                    class="font-semibold text-chrome-fg-muted underline underline-offset-4 hover:text-chrome-fg"
                    @click="enableNotifications"
                >
                    Notify me when time’s up
                </Pressable>
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
