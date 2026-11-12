<script lang="ts">
const dismissedMissed = new Set<number>();
</script>

<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import type { Schedule, Task, TimeBlock } from '@focus/types';
import { useTaskActions } from '@/features/tasks/useTaskActions';
import { formatMinutes, todayIso } from '@/lib/dates';
import {
    clockTime,
    freeMinutes,
    freeSlot,
    missedBlocks,
    missedSummary,
    movedSummary,
    plannedMinutes,
    previousDay,
    snap,
    visibleRange,
} from './timeline';
import { scheduleFor } from './schedules';

const props = defineProps<{ date: string; tasks: Task[]; focusNow?: boolean }>();
const emit = defineEmits<{ changed: [] }>();

const settings = useSettingsStore();
const toast = useToast();
const actions = useTaskActions(() => {
    void load();
    emit('changed');
});
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
const missed = ref<TimeBlock[]>([]);
const movingMissed = ref(false);
const stuck = ref(false);
let carried = false;
const column = ref<HTMLElement | null>(null);
let clock: number | undefined;

const step = computed(() => Number(settings.values.planner.step));
watch(
    () => settings.values.planner.carry_over,
    (on) => {
        if (on) void loadMissed();
    },
);
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
const loaded = ref(false);
const offered = computed(() =>
    loaded.value && !blocks.value.length && props.date === todayIso(now.value)
        ? scheduleFor(schedules.value, props.date)
        : undefined,
);
const offerBusy = ref(false);
const percent = (minute: number) => `${((minute - range.value.start) / span.value) * 100}%`;

async function load(): Promise<void> {
    error.value = null;
    try {
        blocks.value = await api.timeBlocks.list(props.date);
        if (props.focusNow && !loaded.value)
            void nextTick(() => column.value?.querySelector('[data-now]')?.scrollIntoView({ block: 'center' }));
        loaded.value = true;
    } catch (e) {
        error.value = errorMessage(e, 'Could not load the day plan.');
    }
    await loadMissed();
}

async function loadMissed(): Promise<void> {
    if (props.date !== todayIso()) {
        missed.value = [];
        return;
    }
    try {
        missed.value = missedBlocks(await api.timeBlocks.list(previousDay(props.date))).filter(
            (block) => !dismissedMissed.has(block.id),
        );
    } catch {
        missed.value = [];
    }
    if (missed.value.length && settings.values.planner.carry_over && !carried) {
        carried = true;
        await moveMissed(true);
    }
}

async function moveMissed(auto = false): Promise<void> {
    movingMissed.value = true;
    let moved = 0;
    let skipped = 0;
    const taken = [...blocks.value];
    for (const block of missed.value) {
        const start = freeSlot(taken, block.minutes, block.start_minute);
        if (start === null) {
            skipped++;
            continue;
        }
        try {
            taken.push(await api.timeBlocks.update(block.id, { date: props.date, start_minute: start }));
            moved++;
        } catch (e) {
            if (!isApiError(e) || !e.isValidation) {
                toast.error(errorMessage(e, 'Could not move the blocks.'));
                break;
            }
            skipped++;
        }
    }
    if (moved || (skipped && !auto)) toast.success(movedSummary({ moved, skipped }));
    stuck.value = skipped > 0;
    movingMissed.value = false;
    await load();
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

async function planWith(schedule: Schedule): Promise<void> {
    offerBusy.value = true;
    await applySchedule(schedule);
    offerBusy.value = false;
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

function dismissMissed(): void {
    missed.value.forEach((block) => dismissedMissed.add(block.id));
    missed.value = [];
    stuck.value = false;
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

        <Alert v-if="missed.length && !error" icon="history">
            <div class="flex flex-wrap items-center gap-2">
                <span>{{ missedSummary(missed.length, stuck) }}</span>
                <Button v-if="stuck" variant="outline" size="sm" @click="edit(missed[0]!)">Pick a time</Button>
                <Button v-else variant="outline" size="sm" :loading="movingMissed" @click="moveMissed()"
                    >Move to today</Button
                >
                <Button variant="ghost" size="sm" @click="dismissMissed">Dismiss</Button>
            </div>
        </Alert>
        <Alert v-if="offered && !error" icon="calendar-clock">
            <div class="flex flex-wrap items-center gap-2">
                <span>Nothing planned yet. Your “{{ offered.name }}” schedule fits today.</span>
                <Button variant="outline" size="sm" :loading="offerBusy" @click="planWith(offered)"
                    >Plan today with it</Button
                >
            </div>
        </Alert>
        <ErrorState v-if="error" :message="error" @retry="load" />
        <div v-else class="flex gap-3">
            <ol aria-hidden="true" class="flex shrink-0 flex-col">
                <li v-for="hour in hours" :key="hour" class="h-20 -translate-y-2 text-caption text-fg-muted tabular">
                    {{ clockTime(hour) }}
                </li>
            </ol>
            <div ref="column" class="relative flex-1 cursor-copy" @click.self="openAt">
                <div
                    v-for="hour in hours"
                    :key="hour"
                    aria-hidden="true"
                    class="pointer-events-none h-20 border-t border-border"
                />
                <div
                    v-for="block in blocks"
                    :key="block.id"
                    :class="[
                        'absolute inset-x-1 flex gap-2 overflow-clip rounded-sm border-l-4 border-highlight bg-surface-raised pl-2 ring-1 ring-border transition-colors hover:bg-surface-overlay',
                        block.minutes < 30 ? 'items-center' : 'items-start py-1',
                        (block.task?.status === 'completed' || block.task?.status === 'cancelled') && 'opacity-60',
                        block.task?.status === 'cancelled' && 'border-border-control',
                    ]"
                    :style="{
                        top: percent(block.start_minute),
                        height: `calc(${(block.minutes / span) * 100}% - 2px)`,
                    }"
                >
                    <Pressable
                        v-if="block.task"
                        type="button"
                        :aria-label="
                            block.task.status === 'completed'
                                ? `Mark “${block.task.title}” as not done`
                                : block.task.status === 'cancelled'
                                  ? `“${block.task.title}” is cancelled. Reopen it`
                                  : `Mark “${block.task.title}” complete`
                        "
                        :aria-pressed="block.task.status === 'completed'"
                        :class="[
                            'group inline-flex size-(--icon-md) shrink-0 items-center justify-center rounded-full border-2 transition-colors focus-visible:outline-2 focus-visible:outline-focus-ring',
                            block.task.status === 'completed'
                                ? 'border-highlight bg-highlight text-highlight-ink'
                                : block.task.status === 'cancelled'
                                  ? 'border-border-control bg-surface-overlay text-fg-muted hover:text-fg'
                                  : 'border-border-control text-transparent hover:border-highlight-fg hover:text-highlight-fg',
                            block.minutes >= 120 && 'sticky top-16',
                        ]"
                        @click="
                            actions.setStatus(
                                block.task,
                                block.task.status === 'completed' || block.task.status === 'cancelled'
                                    ? 'not_started'
                                    : 'completed',
                            )
                        "
                    >
                        <Icon :name="block.task.status === 'cancelled' ? 'close' : 'check'" size="sm" motion="pop" />
                    </Pressable>
                    <Pressable
                        type="button"
                        :class="[
                            'flex min-w-0 flex-1 cursor-pointer self-stretch pr-2 text-left focus-visible:outline-2 focus-visible:outline-focus-ring',
                            block.minutes < 30 ? 'items-center gap-2' : 'flex-col',
                        ]"
                        :aria-label="`${block.title ?? 'Block'}, ${clockTime(block.start_minute)} to ${clockTime(block.start_minute + block.minutes)}`"
                        @click="edit(block)"
                    >
                        <span
                            :class="[
                                'flex min-w-0 flex-col',
                                block.minutes >= 120 && 'sticky top-16',
                                block.minutes < 30 && 'flex-row items-baseline gap-2',
                            ]"
                        >
                            <span
                                :class="[
                                    'truncate font-semibold',
                                    block.minutes < 30 ? 'text-caption' : 'text-label',
                                    (block.task?.status === 'completed' || block.task?.status === 'cancelled') &&
                                        'line-through',
                                    block.task?.status === 'cancelled' && 'text-fg-muted',
                                ]"
                                >{{ block.title ?? 'Untitled' }}</span
                            >
                            <span
                                v-if="block.task?.status === 'cancelled'"
                                class="shrink-0 text-caption font-semibold text-fg-muted"
                                >Cancelled</span
                            >
                            <span class="shrink-0 truncate text-caption text-fg-muted tabular"
                                >{{ clockTime(block.start_minute) }}–{{ clockTime(block.start_minute + block.minutes)
                                }}<template v-if="block.minutes >= 120">
                                    · {{ formatMinutes(block.minutes) }}</template
                                ></span
                            >
                        </span>
                    </Pressable>
                </div>
                <div
                    v-if="showNow"
                    data-now
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
