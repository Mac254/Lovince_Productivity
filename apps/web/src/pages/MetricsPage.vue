<script setup lang="ts">
import { api } from '@focus/api-client';
import type { TabItem } from '@focus/ui';
import type { MetricRange } from '@focus/types';
import { formatDate, formatMinutes } from '@/lib/dates';
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

const tab = useTabQuery(['trend', 'focus', 'breakdown', 'goals'] as const, 'trend');
const tabs: TabItem<typeof tab.value>[] = [
    { value: 'trend', label: 'Trend', icon: 'metrics' },
    { value: 'focus', label: 'Focus', icon: 'clock' },
    { value: 'breakdown', label: 'Breakdown', icon: 'tag' },
    { value: 'goals', label: 'Goals', icon: 'target' },
];
const goalPage = usePagedList(() => data.value?.goals ?? [], 10);

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
const typeLabel = (row: { category: string | null; label: string }) =>
    row.category ? taskCategoryLabels[row.category as keyof typeof taskCategoryLabels] : row.label;
const typeRows = computed(() =>
    (data.value?.by_type ?? [])
        .filter((row) => row.completed + row.open > 0)
        .map((row) => ({ label: typeLabel(row), completed: row.completed, open: row.open })),
);
const typeTime = computed(() => {
    const rows = (data.value?.by_type ?? [])
        .filter((row) => (row.minutes ?? 0) > 0)
        .map((row) => ({ key: row.key, label: typeLabel(row), minutes: row.minutes ?? 0 }))
        .sort((a, b) => b.minutes - a.minutes);
    const most = Math.max(1, ...rows.map((row) => row.minutes));
    return rows.map((row) => ({ ...row, share: (row.minutes / most) * 100 }));
});
const hasActivity = computed(
    () => !!data.value && data.value.daily.some((d) => d.completed || d.created || d.logged || d.focus),
);

/** Under two hours reads best in minutes; beyond that, whole hours. */
const timeLogged = computed(() => {
    const minutes = data.value?.totals.minutes_logged ?? 0;
    return minutes < 120 ? { value: minutes, suffix: 'm' } : { value: Math.round(minutes / 60), suffix: 'h' };
});
const timeHint = computed(() => {
    if (!data.value) return '';
    const { entries_logged: entries, minutes_logged: minutes, minutes_logged_previous: previous } = data.value.totals;
    if (!entries) return 'Nothing logged in this period';
    const count = `${entries} ${entries === 1 ? 'entry' : 'entries'}`;
    const exact = minutes >= 120 ? ` · ${formatMinutes(minutes)}` : '';
    if (!previous) return `${count}${exact}`;
    const change = Math.round(((minutes - previous) / previous) * 100);
    return `${count}${exact} · ${change >= 0 ? '+' : ''}${change}% vs previous`;
});
const settings = useSettingsStore();
const estimateHint = computed(() => {
    const totals = data.value?.totals;
    if (!totals?.estimated_tasks) return 'No completed tasks with an estimate in this period';
    return `${formatMinutes(totals.estimated_logged_minutes) || '0m'} logged against ${formatMinutes(totals.estimated_minutes)} estimated on ${totals.estimated_tasks} completed ${totals.estimated_tasks === 1 ? 'task' : 'tasks'}`;
});
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
            <section aria-label="Totals" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <StatCard
                    class="lg:col-span-3"
                    label="Completed"
                    icon="check-circle"
                    :value="data.totals.completed"
                    :hint="delta"
                    emphasis
                />
                <StatCard
                    class="lg:col-span-3"
                    label="Time logged"
                    icon="history"
                    :value="timeLogged.value"
                    :suffix="timeLogged.suffix"
                    :hint="timeHint"
                />
                <StatCard
                    v-if="settings.values.tasks.estimates"
                    class="sm:col-span-2 lg:col-span-6"
                    label="Estimate accuracy"
                    icon="target"
                    :value="data.totals.estimate_accuracy"
                    suffix="%"
                    :hint="estimateHint"
                />
                <StatCard
                    class="lg:col-span-2"
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
                    class="lg:col-span-2"
                    label="Median cycle time"
                    icon="hourglass"
                    :value="data.totals.median_cycle_days === null ? null : Math.round(data.totals.median_cycle_days)"
                    suffix="d"
                    hint="Created → completed"
                />
                <StatCard
                    class="sm:col-span-2 lg:col-span-2"
                    label="Open blockers"
                    icon="blocker"
                    motion="wiggle"
                    :value="data.totals.open_blockers"
                    :hint="`${data.totals.blockers_resolved} resolved in range · ${data.totals.overdue_now} tasks overdue`"
                />
            </section>

            <Tabs v-model="tab" label="Metric sections" :tabs="tabs">
                <template #trend>
                    <Card v-if="data.insights.length" as="section" aria-labelledby="insights-heading" class="group">
                        <CardHeader title="Insights" title-id="insights-heading" icon="flame" />
                        <InsightList :insights="data.insights" />
                    </Card>

                    <Card as="section" aria-labelledby="trend-heading" class="group">
                        <CardHeader
                            title="Daily trend"
                            title-id="trend-heading"
                            icon="metrics"
                            :subtitle="`${formatDate(data.range.start, { dateStyle: 'medium' })} – ${formatDate(data.range.end, { dateStyle: 'medium' })} · ${data.totals.per_day} completed per day · ${data.totals.entries_logged} work ${data.totals.entries_logged === 1 ? 'entry' : 'entries'}`"
                        >
                            <Pressable
                                type="button"
                                class="text-label font-semibold text-fg-muted underline-offset-4 hover:text-fg hover:underline"
                                @click="showTable = !showTable"
                            >
                                {{ showTable ? 'Show chart' : 'View as table' }}
                            </Pressable>
                        </CardHeader>
                        <template v-if="!showTable">
                            <DailyTrendChart v-if="hasActivity" :daily="data.daily" />
                            <EmptyState
                                v-else
                                icon="metrics"
                                title="No activity in this period"
                                message="Create, complete or log work on tasks to see the trend."
                            />
                        </template>
                        <Table
                            v-else
                            caption="Tasks created and completed, work logged and focus time, per day"
                            label="Daily numbers"
                            class="max-h-80 overflow-auto"
                        >
                            <TableHeader class="sticky top-0 bg-surface">
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead align="end">Created</TableHead>
                                    <TableHead align="end">Completed</TableHead>
                                    <TableHead align="end">Work logged</TableHead>
                                    <TableHead align="end">Time</TableHead>
                                    <TableHead align="end">Focus</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="row in [...data.daily].reverse()" :key="row.date">
                                    <TableHead scope="row">
                                        {{ formatDate(row.date, { weekday: 'short', day: 'numeric', month: 'short' }) }}
                                    </TableHead>
                                    <TableCell align="end" numeric>{{ row.created }}</TableCell>
                                    <TableCell align="end" numeric class="font-semibold">{{ row.completed }}</TableCell>
                                    <TableCell align="end" numeric>{{ row.logged }}</TableCell>
                                    <TableCell align="end" numeric>{{ formatMinutes(row.minutes) || '—' }}</TableCell>
                                    <TableCell align="end" numeric>{{ formatMinutes(row.focus) || '—' }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </Card>
                </template>
                <template #focus>
                    <Card as="section" aria-labelledby="focus-heading" class="group">
                        <CardHeader
                            title="Focus time"
                            title-id="focus-heading"
                            icon="clock"
                            subtitle="Focus sessions from the clock, finished or stopped after a minute"
                        />
                        <FocusSummary :focus="data.focus" :days="data.range.days" />
                    </Card>

                    <Card as="section" aria-labelledby="heatmap-heading" class="group">
                        <CardHeader
                            title="Activity"
                            title-id="heatmap-heading"
                            icon="flame"
                            subtitle="Tasks completed and work logged per day, last six months"
                        />
                        <ActivityHeatmap :days="data.heatmap" />
                    </Card>
                </template>
                <template #breakdown>
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
                            <div v-if="typeTime.length" class="flex flex-col gap-3 border-t border-border pt-4">
                                <Text role="caption" tone="muted" weight="semibold">Time logged</Text>
                                <dl class="flex flex-col gap-3">
                                    <div v-for="row in typeTime" :key="row.key" class="flex flex-col gap-1">
                                        <div class="flex items-baseline justify-between gap-3">
                                            <dt class="min-w-0 truncate text-label font-semibold">{{ row.label }}</dt>
                                            <dd class="text-label text-fg-muted tabular">
                                                {{ formatMinutes(row.minutes) }}
                                            </dd>
                                        </div>
                                        <div class="h-1 overflow-hidden rounded-full bg-border" aria-hidden="true">
                                            <div class="time-bar h-full" :style="{ width: `${row.share}%` }" />
                                        </div>
                                    </div>
                                </dl>
                            </div>
                        </Card>
                    </div>
                </template>
                <template #goals>
                    <Card as="section" aria-labelledby="goal-progress-heading" class="group">
                        <CardHeader title="Goal progress" title-id="goal-progress-heading" icon="target" />
                        <ul v-if="data.goals.length" class="grid gap-x-8 gap-y-6 md:grid-cols-2">
                            <li v-for="goal in goalPage.visible" :key="goal.id" class="flex flex-col gap-2">
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
                        <Pagination v-model:page="goalPage.page" :last-page="goalPage.lastPage" />
                    </Card>
                </template>
            </Tabs>
        </div>
    </div>
</template>

<style scoped>
.time-bar {
    background: var(--chart-3);
}
</style>
