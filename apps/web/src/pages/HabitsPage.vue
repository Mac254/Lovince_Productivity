<script setup lang="ts">
import { api } from '@focus/api-client';
import type { HabitWindow } from '@focus/types';
import { formatDate } from '@/lib/dates';
import { categoryClass } from '@/features/time/tones';
import { dayTone, dayWord, overallRate, todayTally } from '@/features/habits/days';

const period = ref<'14' | '30' | '90'>('30');
const windows = [
    { value: '14' as const, label: '2 weeks' },
    { value: '30' as const, label: '30 days' },
    { value: '90' as const, label: '90 days' },
];

const { data, loading, error, run } = useAsync((signal) =>
    api.habits.get(Number(period.value) as HabitWindow, { signal }),
);
watch(period, () => void run());
onMounted(run);

const current = computed(() => data.value?.habits.filter((habit) => habit.scheduled) ?? []);
const past = computed(() => data.value?.habits.filter((habit) => !habit.scheduled) ?? []);
const tally = computed(() => todayTally(current.value));
const rate = computed(() => overallRate(current.value));
const leader = computed(() => [...current.value].sort((a, b) => b.streak - a.streak)[0]);
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Habits"
            title-id="page-title"
            description="The routines your schedules lay out each day: how often each one happens and how long the streak runs."
        >
            <template #actions>
                <SegmentedControl v-model="period" label="Period" :options="windows" />
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="loading && !data" class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3" aria-busy="true">
            <Skeleton v-for="n in 6" :key="n" class="h-48" />
        </div>
        <EmptyState
            v-else-if="data && !data.habits.length"
            icon="flame"
            title="No habits yet"
            message="Plan a day with a schedule and its activities show up here as habits."
        >
            <Button :to="{ name: 'schedules' }" variant="outline">Open schedules</Button>
        </EmptyState>

        <div
            v-else-if="data"
            :class="['flex flex-col gap-6 transition-opacity duration-(--duration-fast)', loading && 'opacity-60']"
        >
            <Card as="section" aria-label="Summary">
                <dl class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div class="flex flex-col-reverse gap-1">
                        <dt class="text-caption text-fg-muted">Done today</dt>
                        <dd class="font-display text-display-sm font-semibold tracking-tight tabular">
                            {{ tally.done }}<span class="text-fg-muted"> / {{ tally.due }}</span>
                        </dd>
                    </div>
                    <div class="flex flex-col-reverse gap-1">
                        <dt class="text-caption text-fg-muted">Kept since {{ formatDate(data.from) }}</dt>
                        <dd class="font-display text-display-sm font-semibold tracking-tight tabular">
                            {{ rate === null ? '–' : `${rate}%` }}
                        </dd>
                    </div>
                    <div class="flex flex-col-reverse gap-1">
                        <dt class="truncate text-caption text-fg-muted">
                            Longest run{{ leader?.streak ? ` · ${leader.title}` : '' }}
                        </dt>
                        <dd class="font-display text-display-sm font-semibold tracking-tight tabular">
                            {{ leader?.streak ?? 0 }} {{ leader?.streak === 1 ? 'day' : 'days' }}
                        </dd>
                    </div>
                </dl>
            </Card>

            <ul class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3" aria-label="Habits">
                <li v-for="habit in current" :key="habit.title">
                    <Card as="article" class="h-full gap-4" :aria-label="habit.title">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 flex-col gap-1">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span
                                        :class="[
                                            'size-2 shrink-0 rounded-full',
                                            categoryClass(habit.type ?? { tone: null }),
                                        ]"
                                        aria-hidden="true"
                                    />
                                    <Heading :level="3" role="title" class="truncate">{{ habit.title }}</Heading>
                                </span>
                                <Text role="caption" tone="muted" class="truncate">
                                    <template v-if="habit.alternatives.length">
                                        <Icon name="shuffle" size="sm" class="inline align-text-bottom" />
                                        Mixed · {{ habit.alternatives.length }} activities<template
                                            v-if="habit.last_done && habit.last_done !== habit.title"
                                        >
                                            · last: {{ habit.last_done }}</template
                                        >
                                    </template>
                                    <template v-else>{{ habit.type?.name ?? 'Routine' }}</template>
                                </Text>
                            </div>
                            <span class="shrink-0 font-display text-heading font-semibold tabular">
                                {{ habit.rate === null ? '–' : `${habit.rate}%` }}
                            </span>
                        </div>

                        <dl class="grid grid-cols-3 gap-3">
                            <div class="flex flex-col-reverse gap-1">
                                <dt class="text-caption text-fg-muted">Streak</dt>
                                <dd class="flex items-center gap-1 text-label font-semibold tabular">
                                    <Icon
                                        name="flame"
                                        size="sm"
                                        :class="habit.streak ? 'text-highlight-ink' : 'text-fg-muted'"
                                    />
                                    {{ habit.streak }}
                                </dd>
                            </div>
                            <div class="flex flex-col-reverse gap-1">
                                <dt class="text-caption text-fg-muted">Best</dt>
                                <dd class="text-label font-semibold tabular">{{ habit.best_streak }}</dd>
                            </div>
                            <div class="flex flex-col-reverse gap-1">
                                <dt class="text-caption text-fg-muted">Done</dt>
                                <dd class="text-label font-semibold tabular">
                                    {{ habit.done }}<span class="text-fg-muted"> of {{ habit.planned }}</span>
                                </dd>
                            </div>
                        </dl>

                        <ol class="mt-auto flex flex-wrap gap-1" :aria-label="`${habit.title}, day by day`">
                            <li
                                v-for="day in habit.days"
                                :key="day.date"
                                :class="['size-3 rounded-sm', dayTone[day.status]]"
                                :title="`${formatDate(day.date)}: ${dayWord[day.status]}`"
                            >
                                <span class="sr-only">{{ formatDate(day.date) }}: {{ dayWord[day.status] }}</span>
                            </li>
                        </ol>
                    </Card>
                </li>
            </ul>

            <ul class="flex flex-wrap gap-x-4 gap-y-2" aria-label="Legend">
                <li
                    v-for="status in ['done', 'short', 'skipped', 'missed', 'open'] as const"
                    :key="status"
                    class="flex items-center gap-2 text-caption text-fg-muted"
                >
                    <span :class="['size-3 rounded-sm', dayTone[status]]" aria-hidden="true" />
                    {{ dayWord[status] }}
                </li>
            </ul>

            <Card v-if="past.length" as="section" aria-labelledby="past-heading">
                <CardHeader
                    title="No longer scheduled"
                    title-id="past-heading"
                    icon="history"
                    subtitle="Older routines with history in this period."
                />
                <ul class="flex flex-col divide-y divide-border">
                    <li
                        v-for="habit in past"
                        :key="habit.title"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <span class="truncate text-label font-semibold">{{ habit.title }}</span>
                        <span class="shrink-0 text-label text-fg-muted tabular">
                            {{ habit.done }} of {{ habit.planned }} · {{ habit.rate === null ? '–' : `${habit.rate}%` }}
                        </span>
                    </li>
                </ul>
            </Card>
        </div>
    </div>
</template>
