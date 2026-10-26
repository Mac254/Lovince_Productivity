<script setup lang="ts">
import { api } from '@focus/api-client';
import type { MetricRange } from '@focus/types';
import {
    Card,
    CardHeader,
    ChipGroup,
    EmptyState,
    ErrorState,
    PageHeader,
    ProgressBar,
    Skeleton,
    Text,
} from '@focus/ui';
import { computed, onMounted, ref, watch } from 'vue';
import { useAsync } from '@/composables/useAsync';
import StatCard from '@/features/dashboard/StatCard.vue';
import ActivityHeatmap from '@/features/metrics/ActivityHeatmap.vue';
import BreakdownChart from '@/features/metrics/BreakdownChart.vue';
import DailyTrendChart from '@/features/metrics/DailyTrendChart.vue';
import { formatDate } from '@/lib/dates';
import { taskCategoryLabels, taskPriorityLabels } from '@/lib/labels';

const range = ref<'7' | '30' | '90'>('30');
const ranges = [
    { value: '7' as const, label: 'Last 7 days' },
    { value: '30' as const, label: 'Last 30 days' },
    { value: '90' as const, label: 'Last 90 days' },
];

const { data, loading, error, run } = useAsync((signal) =>
    api.metrics.get(Number(range.value) as MetricRange, { signal }),
);

const delta = computed(() => {
    if (!data.value) return '';
    const { completed, completed_previous: previous } = data.value.totals;
    if (previous === 0) return completed ? 'No completions in the previous period' : 'Same as the previous period';
    const change = Math.round(((completed - previous) / previous) * 100);
    return `${change >= 0 ? '+' : ''}${change}% vs previous ${range.value} days`;
});

const priorityRows = computed(() =>
    (data.value?.by_priority ?? [])
        .map((row) => ({ label: taskPriorityLabels[row.key], completed: row.completed, open: row.open }))
        .reverse(),
);
const typeRows = computed(() =>
    (data.value?.by_type ?? []).map((row) => ({
        label: row.category ? taskCategoryLabels[row.category] : row.label,
        completed: row.completed,
        open: row.open,
    })),
);
const hasActivity = computed(() => !!data.value && data.value.daily.some((d) => d.completed || d.created));
const showTable = ref(false);

watch(range, run);
onMounted(run);
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Metrics"
            title-id="page-title"
            description="What happened over the period. These describe activity, not the quality of the work."
        />

        <ChipGroup v-model="range" label="Time range" :options="ranges" />

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="!data" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-busy="true">
            <Skeleton v-for="n in 4" :key="n" class="h-36" />
            <Skeleton class="h-80 sm:col-span-2 xl:col-span-4" />
        </div>

        <div
            v-else
            :class="['flex flex-col gap-6 transition-opacity duration-(--duration-fast)', loading && 'opacity-60']"
        >
            <section aria-label="Totals" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Completed" icon="check-circle" :value="data.totals.completed" :hint="delta" emphasis />
                <StatCard
                    label="On-time rate"
                    icon="calendar-check"
                    motion="lift"
                    :value="data.totals.on_time_rate"
                    suffix="%"
                    :hint="
                        data.totals.on_time_sample
                            ? `${data.totals.on_time_sample} completed tasks had a due date`
                            : 'No completed tasks with due dates'
                    "
                />
                <StatCard
                    label="Median cycle time"
                    icon="hourglass"
                    :value="data.totals.median_cycle_days === null ? null : Math.round(data.totals.median_cycle_days)"
                    suffix="d"
                    hint="Created → completed"
                />
                <StatCard
                    label="Open blockers"
                    icon="blocker"
                    motion="wiggle"
                    :value="data.totals.open_blockers"
                    :hint="`${data.totals.blockers_resolved} resolved in range · ${data.totals.overdue_now} tasks overdue`"
                />
            </section>

            <Card as="section" aria-labelledby="trend-heading" class="group">
                <CardHeader
                    title="Completed vs created"
                    title-id="trend-heading"
                    icon="metrics"
                    :subtitle="`${formatDate(data.range.start, { dateStyle: 'medium' })} – ${formatDate(data.range.end, { dateStyle: 'medium' })} · ${data.totals.per_day} completed per day`"
                >
                    <button
                        type="button"
                        class="text-label font-semibold text-fg-muted underline-offset-4 hover:text-fg hover:underline"
                        @click="showTable = !showTable"
                    >
                        {{ showTable ? 'Show chart' : 'View as table' }}
                    </button>
                </CardHeader>
                <template v-if="!showTable">
                    <DailyTrendChart v-if="hasActivity" :daily="data.daily" />
                    <EmptyState
                        v-else
                        icon="metrics"
                        title="No activity in this period"
                        message="Create or complete tasks to see the trend."
                    />
                </template>
                <div v-else class="max-h-80 overflow-auto">
                    <table class="w-full text-label">
                        <caption class="sr-only">
                            Tasks created and completed per day
                        </caption>
                        <thead class="sticky top-0 bg-surface text-left text-fg-muted">
                            <tr>
                                <th scope="col" class="py-2 font-semibold">Date</th>
                                <th scope="col" class="py-2 text-right font-semibold">Created</th>
                                <th scope="col" class="py-2 text-right font-semibold">Completed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in [...data.daily].reverse()" :key="row.date">
                                <th scope="row" class="py-2 text-left font-medium">
                                    {{ formatDate(row.date, { weekday: 'short', day: 'numeric', month: 'short' }) }}
                                </th>
                                <td class="py-2 text-right tabular">{{ row.created }}</td>
                                <td class="py-2 text-right font-semibold tabular">{{ row.completed }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card as="section" aria-labelledby="heatmap-heading" class="group">
                <CardHeader
                    title="Activity"
                    title-id="heatmap-heading"
                    icon="flame"
                    subtitle="Tasks completed per day, last six months"
                />
                <ActivityHeatmap :days="data.heatmap" />
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card as="section" aria-labelledby="priority-heading" class="group">
                    <CardHeader title="By priority" title-id="priority-heading" icon="alert-circle" />
                    <BreakdownChart :rows="priorityRows" />
                </Card>
                <Card as="section" aria-labelledby="type-heading" class="group">
                    <CardHeader
                        title="By type"
                        title-id="type-heading"
                        icon="tag"
                        subtitle="Your types, then built-in ones for untyped tasks"
                    />
                    <BreakdownChart v-if="typeRows.length" :rows="typeRows" />
                    <Text v-else role="label" tone="muted" class="py-4">No tasks in this range.</Text>
                </Card>
            </div>

            <Card as="section" aria-labelledby="goal-progress-heading" class="group">
                <CardHeader title="Goal progress" title-id="goal-progress-heading" icon="target" />
                <ul v-if="data.goals.length" class="grid gap-x-8 gap-y-6 md:grid-cols-2">
                    <li v-for="goal in data.goals" :key="goal.id" class="flex flex-col gap-2">
                        <div class="flex items-baseline justify-between gap-3">
                            <RouterLink
                                :to="{ name: 'goal-detail', params: { id: goal.id } }"
                                class="truncate font-semibold underline-offset-4 hover:underline"
                            >
                                {{ goal.title }}
                            </RouterLink>
                            <Text as="span" role="label" tone="muted" tabular class="shrink-0"
                                >{{ goal.completed }}/{{ goal.total }} · {{ goal.progress }}%</Text
                            >
                        </div>
                        <ProgressBar :value="goal.progress" :label="`${goal.title} progress`" />
                    </li>
                </ul>
                <Text v-else role="label" tone="muted">No active goals.</Text>
            </Card>
        </div>
    </div>
</template>
