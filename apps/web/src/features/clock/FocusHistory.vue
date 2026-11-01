<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { FocusSession } from '@focus/types';
import { useAsync } from '@/composables/useAsync';
import { formatMinutes, parseDate, todayIso } from '@/lib/dates';

const open = defineModel<boolean>('open', { required: true });

const timer = useTimerStore();
const toast = useToast();
const date = ref(todayIso());
const removing = ref<number | null>(null);
const history = useAsync((signal) => api.focusSessions.list(date.value, { signal }));

const meta = computed(() => history.data.value?.meta);
const sessions = computed(() => history.data.value?.data ?? []);
const isToday = computed(() => date.value === todayIso());
const peak = computed(() => Math.max(1, ...(meta.value?.week.map((day) => day.minutes) ?? [])));
const weekMinutes = computed(() => meta.value?.week.reduce((sum, day) => sum + day.minutes, 0) ?? 0);
const activeDays = computed(() => meta.value?.week.filter((day) => day.minutes > 0).length ?? 0);

const dayTitle = computed(() =>
    isToday.value
        ? 'Today'
        : new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long' }).format(
              parseDate(date.value),
          ),
);

function weekday(value: string): string {
    return new Intl.DateTimeFormat(undefined, { weekday: 'narrow' }).format(parseDate(value));
}

function clock(value: string): string {
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(value));
}

function total(minutes: number | undefined): string {
    return formatMinutes(minutes) || '0m';
}

function shift(days: number): void {
    const next = parseDate(date.value);
    next.setDate(next.getDate() + days);
    const iso = todayIso(next);
    if (iso <= todayIso()) date.value = iso;
}

async function remove(session: FocusSession): Promise<void> {
    removing.value = session.id;
    try {
        await api.focusSessions.remove(session.id);
        toast.success(session.task ? 'Session and its log entry removed' : 'Session removed');
        await history.run();
    } catch (error) {
        toast.error(errorMessage(error, 'Could not remove the session.'));
    } finally {
        removing.value = null;
    }
}

watch(open, (value) => {
    if (!value) return;
    date.value = todayIso();
    void history.run();
});
watch(date, () => open.value && void history.run());
watch(
    () => timer.recorded,
    () => open.value && isToday.value && void history.run(),
);
</script>

<template>
    <Sheet
        v-model:open="open"
        title="Focus history"
        description="Sessions you finished or stopped after a minute."
        tone="chrome"
    >
        <section aria-labelledby="history-week" class="flex flex-col gap-4">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="history-week" class="text-caption font-semibold tracking-wide text-chrome-fg-muted uppercase">
                    Last 7 days
                </h2>
                <p class="text-label tabular text-chrome-fg-muted">
                    {{ total(weekMinutes) }} · {{ activeDays }}/7 days
                </p>
            </div>
            <ol class="grid h-28 grid-cols-7 items-end gap-2">
                <li v-for="day in meta?.week ?? []" :key="day.date" class="flex h-full">
                    <Pressable
                        type="button"
                        :aria-pressed="day.date === date"
                        :aria-label="`${day.date}: ${total(day.minutes)} in ${day.count} sessions`"
                        class="group flex flex-1 flex-col items-center justify-end gap-2"
                        @click="date = day.date"
                    >
                        <span class="flex w-full flex-1 items-end">
                            <span
                                :class="[
                                    'bar w-full rounded-sm transition-colors',
                                    day.date === date
                                        ? 'bg-highlight'
                                        : 'bg-chrome-border group-hover:bg-chrome-fg-muted',
                                ]"
                                :style="{ height: `${Math.max(4, (day.minutes / peak) * 100)}%` }"
                            />
                        </span>
                        <span
                            :class="[
                                'text-caption font-semibold',
                                day.date === date ? 'text-chrome-fg' : 'text-chrome-fg-muted',
                            ]"
                        >
                            {{ weekday(day.date) }}
                        </span>
                    </Pressable>
                </li>
            </ol>
        </section>

        <section aria-labelledby="history-day" class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-3">
                <h2 id="history-day" class="font-display text-title font-medium">{{ dayTitle }}</h2>
                <div class="flex gap-1">
                    <IconButton icon="chevron-left" label="Previous day" variant="ghost" size="sm" @click="shift(-1)" />
                    <IconButton
                        icon="chevron-right"
                        label="Next day"
                        variant="ghost"
                        size="sm"
                        :disabled="isToday"
                        @click="shift(1)"
                    />
                </div>
            </div>

            <dl class="grid grid-cols-3 gap-2">
                <div
                    v-for="stat in [
                        { label: 'Focused', value: total(meta?.minutes) },
                        { label: 'Sessions', value: String(meta?.count ?? 0) },
                        { label: 'Completed', value: String(meta?.completed ?? 0) },
                    ]"
                    :key="stat.label"
                    class="flex flex-col gap-1 rounded-md bg-chrome-hover p-3"
                >
                    <dt class="text-caption text-chrome-fg-muted">{{ stat.label }}</dt>
                    <dd class="font-display text-title font-medium tabular">{{ stat.value }}</dd>
                </div>
            </dl>

            <ErrorState v-if="history.error.value" :message="history.error.value" @retry="history.run()" />
            <div v-else-if="history.loading.value && !history.data.value" class="flex flex-col gap-2">
                <Skeleton v-for="n in 3" :key="n" class="h-14 rounded-md" />
            </div>
            <p
                v-else-if="!sessions.length"
                class="rounded-md border border-dashed border-chrome-border p-6 text-center text-label text-chrome-fg-muted"
            >
                {{ isToday ? 'No focus sessions yet today.' : 'No focus sessions on this day.' }}
            </p>
            <ul v-else class="flex flex-col divide-y divide-chrome-border">
                <li v-for="session in sessions" :key="session.id" class="flex items-center gap-3 py-3">
                    <span
                        :class="[
                            'size-2 shrink-0 rounded-full',
                            session.completed ? 'bg-highlight' : 'bg-chrome-fg-muted',
                        ]"
                        aria-hidden="true"
                    />
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <p class="text-label font-semibold tabular">
                            {{ clock(session.started_at) }} – {{ clock(session.ended_at) }}
                            <span class="font-normal text-chrome-fg-muted">
                                · {{ total(session.minutes) }}
                                {{ session.completed ? '' : `of ${session.planned_minutes}m` }}
                            </span>
                        </p>
                        <p class="truncate text-caption text-chrome-fg-muted">
                            {{ session.task?.title ?? 'No task' }}
                            {{ session.completed ? '' : '· Stopped early' }}
                        </p>
                    </div>
                    <IconButton
                        icon="trash"
                        :label="`Remove the ${clock(session.started_at)} session`"
                        variant="ghost"
                        size="sm"
                        :disabled="removing === session.id"
                        @click="remove(session)"
                    />
                </li>
            </ul>
        </section>
    </Sheet>
</template>

<style scoped>
.bar {
    transition: height var(--duration-normal) var(--ease-out);
}
</style>
