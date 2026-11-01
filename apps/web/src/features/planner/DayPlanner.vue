<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import type { Schedule, Task, TimeBlock } from '@focus/types';
import { formatMinutes, todayIso } from '@/lib/dates';
import { clockTime, freeMinutes, plannedMinutes, snap, visibleRange } from './timeline';

const props = defineProps<{ date: string; tasks: Task[] }>();

const settings = useSettingsStore();
const toast = useToast();
const blocks = ref<TimeBlock[]>([]);
const schedules = ref<Schedule[]>([]);
const replacing = ref<{ schedule: Schedule; message: string } | null>(null);
const replaceBusy = ref(false);
const error = ref<string | null>(null);
const dialogOpen = ref(false);
const editing = ref<TimeBlock | null>(null);
const draftStart = ref(0);
const draftMinutes = ref(60);
const now = ref(new Date());
const column = ref<HTMLElement | null>(null);
let clock: number | undefined;

const step = computed(() => Number(settings.values.planner.step));
const range = computed(() =>
    visibleRange(Number(settings.values.planner.day_start), Number(settings.values.planner.day_end), blocks.value),
);
const span = computed(() => range.value.end - range.value.start);
const hours = computed(() => Array.from({ length: span.value / 60 }, (_, index) => range.value.start + index * 60));
const nowMinute = computed(() => now.value.getHours() * 60 + now.value.getMinutes());
const showNow = computed(
    () =>
        props.date === todayIso(now.value) && nowMinute.value >= range.value.start && nowMinute.value < range.value.end,
);
const planned = computed(() => formatMinutes(plannedMinutes(blocks.value)));
const percent = (minute: number) => `${((minute - range.value.start) / span.value) * 100}%`;

async function load(): Promise<void> {
    error.value = null;
    try {
        blocks.value = await api.timeBlocks.list(props.date);
    } catch (e) {
        error.value = errorMessage(e, 'Could not load the day plan.');
    }
}

const scheduleMenu = computed(() =>
    schedules.value.map((schedule) => ({
        label: schedule.name,
        icon: 'calendar-clock' as const,
        onSelect: () => void applySchedule(schedule),
    })),
);

async function loadSchedules(): Promise<void> {
    try {
        schedules.value = await api.schedules.list();
    } catch {
        schedules.value = [];
    }
}

async function applySchedule(schedule: Schedule, replace = false): Promise<void> {
    try {
        await api.schedules.apply(schedule.id, props.date, replace);
        replacing.value = null;
        toast.success(`“${schedule.name}” planned`);
        await load();
    } catch (e) {
        if (isApiError(e) && e.isValidation && !replace)
            replacing.value = { schedule, message: e.fieldErrors().date ?? '' };
        else toast.error(errorMessage(e, 'Could not apply the schedule.'));
    }
}

async function confirmReplace(): Promise<void> {
    if (!replacing.value) return;
    replaceBusy.value = true;
    await applySchedule(replacing.value.schedule, true);
    replaceBusy.value = false;
}

function openAt(event: MouseEvent): void {
    if (!column.value) return;
    const box = column.value.getBoundingClientRect();
    const minute = range.value.start + ((event.clientY - box.top) / box.height) * span.value;
    newBlock(snap(minute, step.value));
}

function newBlock(start = snap(Math.max(nowMinute.value, range.value.start), step.value)): void {
    editing.value = null;
    draftStart.value = start;
    draftMinutes.value = Math.max(step.value, freeMinutes(blocks.value, start, step.value));
    dialogOpen.value = true;
}

function edit(block: TimeBlock): void {
    editing.value = block;
    dialogOpen.value = true;
}

watch(() => props.date, load, { immediate: true });
onMounted(() => {
    void loadSchedules();
    clock = window.setInterval(() => (now.value = new Date()), 60_000);
});
onBeforeUnmount(() => window.clearInterval(clock));
</script>

<template>
    <Card as="section" aria-labelledby="planner-heading" class="group">
        <CardHeader
            title="Day plan"
            title-id="planner-heading"
            icon="calendar-clock"
            :subtitle="blocks.length ? `${planned} blocked out` : 'Click the timeline to block out time'"
        >
            <DropdownMenu
                v-if="schedules.length"
                :items="scheduleMenu"
                label="Use a schedule"
                icon="calendar-clock"
                trigger-variant="ghost"
                size="sm"
            />
            <Button variant="ghost" size="sm" icon-start="add" @click="newBlock()">Block</Button>
        </CardHeader>

        <ErrorState v-if="error" :message="error" @retry="load" />
        <div v-else class="flex gap-3">
            <ol aria-hidden="true" class="flex shrink-0 flex-col">
                <li v-for="hour in hours" :key="hour" class="h-14 -translate-y-2 text-caption text-fg-muted tabular">
                    {{ clockTime(hour) }}
                </li>
            </ol>
            <div ref="column" class="relative flex-1 cursor-copy" @click.self="openAt">
                <div
                    v-for="hour in hours"
                    :key="hour"
                    aria-hidden="true"
                    class="pointer-events-none h-14 border-t border-border"
                />
                <Pressable
                    v-for="block in blocks"
                    :key="block.id"
                    type="button"
                    class="absolute inset-x-1 flex min-h-6 cursor-pointer flex-col overflow-hidden rounded-md border border-border border-l-highlight bg-surface-raised px-2 py-1 text-left transition-colors hover:bg-surface-overlay focus-visible:outline-2 focus-visible:outline-focus-ring"
                    :class="[
                        block.task?.status === 'completed' && 'opacity-60',
                        block.minutes >= 45 ? 'border-l-4' : 'border-l-2',
                    ]"
                    :style="{ top: percent(block.start_minute), height: `${(block.minutes / span) * 100}%` }"
                    :aria-label="`${block.title ?? 'Block'}, ${clockTime(block.start_minute)} to ${clockTime(block.start_minute + block.minutes)}`"
                    @click="edit(block)"
                >
                    <span
                        class="truncate text-label font-semibold"
                        :class="block.task?.status === 'completed' && 'line-through'"
                        >{{ block.title ?? 'Untitled' }}</span
                    >
                    <span v-if="block.minutes >= 45" class="truncate text-caption text-fg-muted tabular"
                        >{{ clockTime(block.start_minute) }}–{{ clockTime(block.start_minute + block.minutes) }}</span
                    >
                </Pressable>
                <div
                    v-if="showNow"
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-x-0 border-t-2 border-highlight"
                    :style="{ top: percent(nowMinute) }"
                />
            </div>
        </div>

        <TimeBlockDialog
            v-model:open="dialogOpen"
            :date="date"
            :block="editing"
            :start="draftStart"
            :minutes="draftMinutes"
            :tasks="tasks"
            @changed="load"
        />
        <ConfirmDialog
            :open="!!replacing"
            title="Replace this day's plan?"
            :message="`${replacing?.message ?? ''} Replacing removes the blocks already planned for this day; their tasks are kept.`"
            confirm-label="Replace plan"
            :loading="replaceBusy"
            @update:open="(value) => !value && (replacing = null)"
            @confirm="confirmReplace"
        />
    </Card>
</template>
