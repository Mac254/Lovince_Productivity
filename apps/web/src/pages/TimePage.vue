<script setup lang="ts">
import { api } from '@focus/api-client';
import type { TimeRange } from '@focus/types';
import { formatDate, formatLongDate, formatMinutes, parseDate, todayIso } from '@/lib/dates';
import TimeChart from '@/features/time/TimeChart.vue';
import { categoryClass, change } from '@/features/time/tones';

const range = ref<TimeRange>('week');
const ranges: { value: TimeRange; label: string }[] = [
    { value: 'day', label: 'Day' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Month' },
];
const date = ref(todayIso());

const { data, loading, error, run } = useAsync((signal) => api.time.get(range.value, date.value, { signal }));
watch([range, date], () => void run());
onMounted(run);

function shift(step: number): void {
    const next = parseDate(date.value);
    if (range.value === 'day') next.setDate(next.getDate() + step);
    else if (range.value === 'week') next.setDate(next.getDate() + step * 7);
    else next.setMonth(next.getMonth() + step, 1);
    date.value = todayIso(next) > todayIso() ? todayIso() : todayIso(next);
}

const isCurrent = computed(() => !data.value || data.value.to >= todayIso());
const previousName = computed(() => ({ day: 'yesterday', week: 'last week', month: 'last month' })[range.value]);

const period = computed(() => {
    const s = data.value;
    if (!s) return '';
    if (s.range === 'day') return s.from === todayIso() ? 'Today' : formatLongDate(s.from);
    if (s.range === 'month') return formatDate(s.from, { month: 'long', year: 'numeric' });
    return `${formatDate(s.from)} – ${formatDate(s.to)}`;
});

const headline = computed(() =>
    data.value ? (range.value === 'day' ? data.value.total : data.value.daily_average) : 0,
);
const delta = computed(() => {
    const s = data.value;
    if (!s) return null;
    return range.value === 'day' ? change(s.total, s.previous_total) : change(s.daily_average, s.previous_average);
});

const categories = computed(() => data.value?.categories ?? []);
const share = (minutes: number) => (data.value?.total ? Math.round((minutes / data.value.total) * 100) : 0);
const largest = computed(() => categories.value[0]?.minutes ?? 0);
const categoryOf = (key: string) => categories.value.find((c) => c.key === key);
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Time"
            title-id="page-title"
            description="Where your hours go, from the day plan: every block that happened, grouped by its type."
        >
            <template #actions>
                <SegmentedControl v-model="range" label="Time range" :options="ranges" />
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="loading && !data" class="flex flex-col gap-6" aria-busy="true">
            <Skeleton class="h-96" />
            <Skeleton class="h-64" />
        </div>

        <div
            v-else-if="data"
            :class="['flex flex-col gap-6 transition-opacity duration-(--duration-fast)', loading && 'opacity-60']"
        >
            <Card as="section" aria-labelledby="time-heading" class="gap-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <Text id="time-heading" role="label" tone="muted">
                            {{ range === 'day' ? 'Tracked' : 'Daily average' }}
                        </Text>
                        <p class="font-display text-display-sm font-semibold tracking-tight tabular">
                            {{ formatMinutes(headline) || '0m' }}
                        </p>
                        <Text v-if="delta !== null" role="label" tone="muted">
                            <span :class="delta >= 0 ? 'text-fg' : 'text-fg-muted'" class="font-semibold">
                                {{ delta >= 0 ? '↑' : '↓' }} {{ Math.abs(delta) }}%
                            </span>
                            from {{ previousName }}
                        </Text>
                    </div>
                    <div class="flex items-center gap-2">
                        <IconButton
                            icon="chevron-left"
                            label="Earlier"
                            variant="outline"
                            size="sm"
                            @click="shift(-1)"
                        />
                        <Text role="label" class="min-w-32 text-center font-semibold">{{ period }}</Text>
                        <IconButton
                            icon="chevron-right"
                            label="Later"
                            variant="outline"
                            size="sm"
                            :disabled="isCurrent"
                            @click="shift(1)"
                        />
                    </div>
                </div>

                <EmptyState
                    v-if="!data.total"
                    icon="history"
                    title="No time tracked yet"
                    message="Blocks on your day plan show up here once their time has passed."
                />
                <template v-else>
                    <TimeChart :summary="data" :categories="categories" :range="range" />
                    <ul class="flex flex-wrap gap-x-6 gap-y-3" aria-label="Categories">
                        <li v-for="category in categories" :key="category.key" class="flex flex-col gap-1">
                            <span class="flex items-center gap-2 text-caption text-fg-muted">
                                <span :class="['size-2 rounded-full', categoryClass(category)]" aria-hidden="true" />
                                {{ category.name }}
                            </span>
                            <span class="text-label font-semibold tabular">{{ formatMinutes(category.minutes) }}</span>
                        </li>
                    </ul>
                    <dl class="grid grid-cols-3 gap-4 border-t border-border pt-4">
                        <div class="flex flex-col-reverse gap-1">
                            <dt class="text-caption text-fg-muted">Total</dt>
                            <dd class="text-lead font-semibold tabular">{{ formatMinutes(data.total) }}</dd>
                        </div>
                        <div class="flex flex-col-reverse gap-1">
                            <dt class="text-caption text-fg-muted">Focus sessions</dt>
                            <dd class="text-lead font-semibold tabular">
                                {{ formatMinutes(data.focus_minutes) || '0m' }}
                            </dd>
                        </div>
                        <div class="flex flex-col-reverse gap-1">
                            <dt class="text-caption text-fg-muted">Top category</dt>
                            <dd class="truncate text-lead font-semibold">{{ categories[0]?.name }}</dd>
                        </div>
                    </dl>
                </template>
            </Card>

            <div v-if="data.total" class="grid gap-6 lg:grid-cols-2">
                <Card as="section" aria-labelledby="categories-heading">
                    <CardHeader title="Categories" title-id="categories-heading" icon="tag" />
                    <ul class="flex flex-col gap-4">
                        <li v-for="category in categories" :key="category.key" class="flex flex-col gap-2">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="flex min-w-0 items-center gap-2 text-label font-semibold">
                                    <span
                                        :class="['size-2 shrink-0 rounded-full', categoryClass(category)]"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{ category.name }}</span>
                                </span>
                                <span class="shrink-0 text-label tabular">
                                    {{ formatMinutes(category.minutes) }}
                                    <span class="text-fg-muted">· {{ share(category.minutes) }}%</span>
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-surface-raised">
                                <div
                                    :class="['h-full rounded-full', categoryClass(category)]"
                                    :style="{ width: `${(category.minutes / largest) * 100}%` }"
                                />
                            </div>
                        </li>
                    </ul>
                </Card>

                <Card as="section" aria-labelledby="activities-heading">
                    <CardHeader title="Top activities" title-id="activities-heading" icon="history" />
                    <ol class="flex flex-col divide-y divide-border">
                        <li
                            v-for="activity in data.activities"
                            :key="activity.title"
                            class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <span
                                :class="[
                                    'inline-flex size-8 shrink-0 items-center justify-center rounded-full text-caption font-semibold text-fg-inverse',
                                    categoryClass(categoryOf(activity.key) ?? { tone: null }),
                                ]"
                                aria-hidden="true"
                                >{{ activity.title.charAt(0).toUpperCase() }}</span
                            >
                            <div class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate text-label font-semibold">{{ activity.title }}</span>
                                <span class="text-caption text-fg-muted">
                                    {{ categoryOf(activity.key)?.name ?? 'Other' }}
                                    <template v-if="range !== 'day'">
                                        · {{ activity.days }} {{ activity.days === 1 ? 'day' : 'days' }}</template
                                    >
                                </span>
                            </div>
                            <span class="shrink-0 text-label font-semibold tabular">{{
                                formatMinutes(activity.minutes)
                            }}</span>
                        </li>
                    </ol>
                </Card>
            </div>
        </div>
    </div>
</template>
