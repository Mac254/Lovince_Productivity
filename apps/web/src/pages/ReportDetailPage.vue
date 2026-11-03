<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { TabItem } from '@focus/ui';
import type { DailyReport } from '@focus/types';
import { dueLabel, formatDate, formatMinutes, todayIso } from '@/lib/dates';
import {
    suggestionCategoryIcons,
    suggestionCategoryLabels,
    suggestionCategoryTones,
    suggestionStatusLabels,
    suggestionStatusTones,
    taskPriorityLabels,
    taskPriorityTones,
} from '@/lib/labels';

const route = useRoute();
const toast = useToast();
const report = ref<DailyReport | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const regenerating = ref(false);

const date = computed(() => String(route.params.date));
const isToday = computed(() => date.value === todayIso());
/** A report saved after its day ended covers the whole day; earlier ones stop at the time they were saved. */
const isFinal = computed(() => {
    const saved = report.value?.generated_at;
    return !!saved && !!report.value && todayIso(new Date(saved)) > report.value.date;
});
const coverage = computed(() => {
    const saved = report.value?.generated_at;
    if (!saved) return '';
    return isFinal.value ? 'Final · covers the whole day' : `As of ${formatDate(saved, { timeStyle: 'short' })}`;
});

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        report.value = await api.reports.get(date.value);
    } catch (e) {
        error.value = errorMessage(e, 'Could not load this report.');
    } finally {
        loading.value = false;
    }
}

async function regenerate(): Promise<void> {
    regenerating.value = true;
    try {
        report.value = await api.reports.generateToday();
        toast.success('Report updated');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not update the report.'));
    } finally {
        regenerating.value = false;
    }
}

watch(date, load, { immediate: true });

const completedPage = usePagedList(() => report.value?.completed_tasks ?? [], 8);
const carriedPage = usePagedList(() => report.value?.carried_over ?? [], 8);
const overduePage = usePagedList(() => report.value?.overdue_tasks ?? [], 8);
const logPage = usePagedList(() => report.value?.work_log ?? [], 10);

const tab = useTabQuery(['tasks', 'log', 'blockers', 'wins', 'goals'] as const, 'tasks');
const tabs = computed<TabItem<typeof tab.value>[]>(() => [
    { value: 'tasks', label: 'Tasks', icon: 'tasks', count: report.value?.completed_tasks.length },
    { value: 'log', label: 'Work log', icon: 'history', count: report.value?.work_log?.length },
    { value: 'blockers', label: 'Blockers', icon: 'blocker', count: report.value?.blockers_open.length },
    {
        value: 'wins',
        label: 'Wins',
        icon: 'star',
        count: (report.value?.achievements?.length ?? 0) + (report.value?.suggestions?.length ?? 0),
    },
    { value: 'goals', label: 'Goals & journal', icon: 'target' },
]);

const plural = (count: number, one: string, many = `${one}s`) => `${count} ${count === 1 ? one : many}`;
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            :title="
                !report
                    ? 'Report'
                    : formatDate(report.date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
            "
            title-id="page-title"
            :description="report?.narrative"
            :back="{ to: { name: 'reports' }, label: 'Back to all reports' }"
        >
            <template v-if="report" #actions>
                <Text role="caption" tone="muted">{{ coverage }}</Text>
                <Button v-if="isToday" variant="outline" icon-start="retry" :loading="regenerating" @click="regenerate"
                    >Refresh</Button
                >
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="load" />
        <Skeleton v-else-if="loading && !report" class="h-96" />

        <template v-else-if="report">
            <section aria-label="Day in numbers" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Completed"
                    icon="check-circle"
                    :value="report.summary.completed"
                    :hint="`${report.summary.high_priority_completed} high priority${report.summary.planned !== undefined ? ` · ${report.summary.created} added` : ''}`"
                    emphasis
                />
                <StatCard
                    label="Due this day"
                    icon="calendar-check"
                    :value="report.summary.completion_rate"
                    suffix="%"
                    :hint="`${report.summary.due_completed} of ${report.summary.due} done`"
                />
                <StatCard
                    v-if="report.summary.planned !== undefined"
                    label="Plan done"
                    icon="checklist"
                    :value="report.summary.planned_completed ?? 0"
                    :hint="`of ${report.summary.planned} planned${report.summary.planned_worked ? ` · ${report.summary.planned_worked} worked on` : ''}`"
                />
                <StatCard v-else label="Created" icon="add" :value="report.summary.created" hint="New tasks added" />
                <StatCard
                    label="Blockers"
                    icon="blocker"
                    motion="wiggle"
                    :value="report.summary.open_blockers"
                    :hint="`${report.summary.blockers_raised} raised · ${report.summary.blockers_resolved} resolved`"
                />
            </section>

            <Tabs v-model="tab" label="Report sections" :tabs="tabs">
                <template #tasks>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <Card as="section" aria-labelledby="done-heading" class="group">
                            <CardHeader
                                title="Completed"
                                title-id="done-heading"
                                icon="check-circle"
                                :subtitle="plural(report.completed_tasks.length, 'task')"
                            />
                            <ul v-if="report.completed_tasks.length" class="flex flex-col divide-y divide-border">
                                <li
                                    v-for="task in completedPage.visible"
                                    :key="task.id"
                                    class="flex items-center justify-between gap-3 py-2"
                                >
                                    <RouterLink
                                        :to="{ name: 'task-detail', params: { id: task.id } }"
                                        class="min-w-0 truncate font-semibold underline-offset-4 hover:underline"
                                        >{{ task.title }}</RouterLink
                                    >
                                    <span class="flex shrink-0 gap-1">
                                        <Badge :tone="taskPriorityTones[task.priority]">{{
                                            taskPriorityLabels[task.priority]
                                        }}</Badge>
                                        <TaskTypeBadge :task="task" />
                                    </span>
                                </li>
                            </ul>
                            <Text v-else role="label" tone="muted">Nothing was completed this day.</Text>
                            <Pagination v-model:page="completedPage.page" :last-page="completedPage.lastPage" />
                        </Card>

                        <Card as="section" aria-labelledby="carried-heading" class="group">
                            <CardHeader
                                title="Carried over"
                                title-id="carried-heading"
                                icon="history"
                                :subtitle="
                                    isFinal ? 'Due this day, still open when it ended' : 'Due this day, still open'
                                "
                            />
                            <ul v-if="report.carried_over.length" class="flex flex-col divide-y divide-border">
                                <li
                                    v-for="task in carriedPage.visible"
                                    :key="task.id"
                                    class="flex items-center justify-between gap-3 py-2"
                                >
                                    <RouterLink
                                        :to="{ name: 'task-detail', params: { id: task.id } }"
                                        class="min-w-0 truncate font-semibold underline-offset-4 hover:underline"
                                        >{{ task.title }}</RouterLink
                                    >
                                    <Badge :tone="taskPriorityTones[task.priority]">{{
                                        taskPriorityLabels[task.priority]
                                    }}</Badge>
                                </li>
                            </ul>
                            <Text v-else role="label" tone="muted">Nothing carried over.</Text>
                            <Pagination v-model:page="carriedPage.page" :last-page="carriedPage.lastPage" />
                        </Card>

                        <Card as="section" aria-labelledby="overdue-heading" class="group">
                            <CardHeader
                                title="Overdue"
                                title-id="overdue-heading"
                                icon="warning"
                                :subtitle="plural(report.summary.overdue, 'task')"
                            />
                            <ul v-if="report.overdue_tasks.length" class="flex flex-col divide-y divide-border">
                                <li
                                    v-for="task in overduePage.visible"
                                    :key="task.id"
                                    class="flex items-center justify-between gap-3 py-2"
                                >
                                    <RouterLink
                                        :to="{ name: 'task-detail', params: { id: task.id } }"
                                        class="min-w-0 truncate font-semibold underline-offset-4 hover:underline"
                                        >{{ task.title }}</RouterLink
                                    >
                                    <Text as="span" role="caption" tone="danger" weight="semibold" tabular>{{
                                        dueLabel(task.due_date, report.date)
                                    }}</Text>
                                </li>
                            </ul>
                            <Text v-else role="label" tone="muted">Nothing was overdue.</Text>
                            <Pagination v-model:page="overduePage.page" :last-page="overduePage.lastPage" />
                        </Card>
                    </div>
                </template>
                <template #blockers>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <Card as="section" aria-labelledby="blockers-heading" class="group">
                            <CardHeader title="Blockers" title-id="blockers-heading" icon="blocker" />
                            <p class="mb-2 text-label font-semibold">Open</p>
                            <ul v-if="report.blockers_open.length" class="mb-4 flex flex-col gap-2">
                                <li
                                    v-for="blocker in report.blockers_open"
                                    :key="blocker.id"
                                    class="rounded-md bg-surface-raised px-3 py-2"
                                >
                                    <Text role="label" class="whitespace-pre-line">{{ blocker.description }}</Text>
                                    <Text v-if="blocker.task" role="caption" tone="muted">{{
                                        blocker.task.title
                                    }}</Text>
                                </li>
                            </ul>
                            <Text v-else role="label" tone="muted" class="mb-4">None open.</Text>
                            <p class="mb-2 text-label font-semibold">Resolved this day</p>
                            <ul v-if="report.blockers_resolved.length" class="flex flex-col gap-2">
                                <li
                                    v-for="blocker in report.blockers_resolved"
                                    :key="blocker.id"
                                    class="rounded-md bg-surface-raised px-3 py-2"
                                >
                                    <Text role="label" class="line-through">{{ blocker.description }}</Text>
                                </li>
                            </ul>
                            <Text v-else role="label" tone="muted">None resolved.</Text>
                        </Card>
                    </div>
                </template>
                <template #log>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <Card as="section" aria-labelledby="worklog-heading" class="group">
                            <CardHeader
                                title="Work log"
                                title-id="worklog-heading"
                                icon="history"
                                :subtitle="
                                    report.summary.notes_logged
                                        ? [
                                              plural(report.summary.notes_logged, 'entry', 'entries'),
                                              formatMinutes(report.summary.minutes_logged),
                                          ]
                                              .filter(Boolean)
                                              .join(' · ')
                                        : 'Notes logged on tasks this day'
                                "
                            />
                            <ul v-if="report.work_log?.length" class="flex flex-col divide-y divide-border">
                                <li v-for="entry in logPage.visible" :key="entry.id" class="flex flex-col gap-1 py-2">
                                    <span class="flex items-baseline justify-between gap-3">
                                        <RouterLink
                                            v-if="entry.task"
                                            :to="{ name: 'task-detail', params: { id: entry.task.id } }"
                                            class="min-w-0 truncate text-label font-semibold underline-offset-4 hover:underline"
                                            >{{ entry.task.title }}</RouterLink
                                        >
                                        <Text v-if="entry.minutes" as="span" role="caption" tone="muted" tabular>{{
                                            formatMinutes(entry.minutes)
                                        }}</Text>
                                    </span>
                                    <Text role="label" tone="muted" class="break-words whitespace-pre-line">{{
                                        entry.body
                                    }}</Text>
                                </li>
                            </ul>
                            <Text v-else role="label" tone="muted">Nothing was logged this day.</Text>
                            <Pagination v-model:page="logPage.page" :last-page="logPage.lastPage" />
                        </Card>

                        <Card as="section" aria-labelledby="types-heading" class="group">
                            <CardHeader
                                title="Done by type"
                                title-id="types-heading"
                                icon="tag"
                                :subtitle="`${report.summary.completed} completed`"
                            />
                            <TypeBreakdownList
                                v-if="report.by_type?.length"
                                :rows="report.by_type"
                                completed-label="done"
                            />
                            <Text v-else role="label" tone="muted">Nothing was completed this day.</Text>
                        </Card>
                    </div>
                </template>
                <template #wins>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <Card as="section" aria-labelledby="achievements-heading" class="group">
                            <CardHeader
                                title="Achievements"
                                title-id="achievements-heading"
                                icon="star"
                                :subtitle="plural(report.achievements?.length ?? 0, 'achievement')"
                            >
                                <Button variant="ghost" size="sm" :to="{ name: 'achievements' }" icon-end="arrow-right"
                                    >Open</Button
                                >
                            </CardHeader>
                            <ul v-if="report.achievements?.length" class="flex flex-col divide-y divide-border">
                                <li
                                    v-for="achievement in report.achievements"
                                    :key="achievement.id"
                                    class="flex items-center justify-between gap-3 py-2"
                                >
                                    <span class="flex min-w-0 flex-col">
                                        <span class="truncate font-semibold">{{ achievement.title }}</span>
                                        <Text v-if="achievement.goal" as="span" role="caption" tone="muted">{{
                                            achievement.goal.title
                                        }}</Text>
                                    </span>
                                    <Badge
                                        v-if="achievement.category"
                                        :tone="suggestionCategoryTones[achievement.category]"
                                        :icon="suggestionCategoryIcons[achievement.category]"
                                        >{{ suggestionCategoryLabels[achievement.category] }}</Badge
                                    >
                                </li>
                            </ul>
                            <Text v-else role="label" tone="muted">No achievements were recorded this day.</Text>
                        </Card>

                        <Card as="section" aria-labelledby="suggestions-heading" class="group">
                            <CardHeader
                                title="Suggestions acted on"
                                title-id="suggestions-heading"
                                icon="lightbulb"
                                :subtitle="`${report.summary.suggestions_done ?? 0} done · ${report.summary.suggestions_accepted ?? 0} accepted`"
                            >
                                <Button variant="ghost" size="sm" :to="{ name: 'suggestions' }" icon-end="arrow-right"
                                    >Open</Button
                                >
                            </CardHeader>
                            <ul v-if="report.suggestions?.length" class="flex flex-col divide-y divide-border">
                                <li
                                    v-for="suggestion in report.suggestions"
                                    :key="suggestion.id"
                                    class="flex items-center justify-between gap-3 py-2"
                                >
                                    <span class="min-w-0 truncate font-semibold">{{ suggestion.title }}</span>
                                    <span class="flex shrink-0 gap-1">
                                        <Badge
                                            :tone="suggestionCategoryTones[suggestion.category]"
                                            :icon="suggestionCategoryIcons[suggestion.category]"
                                            >{{ suggestionCategoryLabels[suggestion.category] }}</Badge
                                        >
                                        <Badge :tone="suggestionStatusTones[suggestion.status]" dot>{{
                                            suggestionStatusLabels[suggestion.status]
                                        }}</Badge>
                                    </span>
                                </li>
                            </ul>
                            <Text v-else role="label" tone="muted">No suggestions were acted on this day.</Text>
                        </Card>
                    </div>
                </template>
                <template #goals>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <Card as="section" aria-labelledby="goals-heading" class="group">
                            <CardHeader
                                title="Goals"
                                title-id="goals-heading"
                                icon="target"
                                :subtitle="isFinal ? 'Progress at the end of the day' : 'Progress so far'"
                            />
                            <ul v-if="report.goals.length" class="flex flex-col gap-4">
                                <li v-for="goal in report.goals" :key="goal.id" class="flex flex-col gap-2">
                                    <div class="flex justify-between gap-3">
                                        <span class="truncate font-semibold">{{ goal.title }}</span>
                                        <Text as="span" role="label" tone="muted" tabular
                                            >{{ goal.completed }}/{{ goal.total }} · {{ goal.progress }}%</Text
                                        >
                                    </div>
                                    <ProgressBar :value="goal.progress" :label="`${goal.title} progress`" />
                                </li>
                            </ul>
                            <Text v-else role="label" tone="muted">No active goals.</Text>
                        </Card>

                        <Card as="section" aria-labelledby="journal-heading" class="group">
                            <CardHeader title="Journal" title-id="journal-heading" icon="journal">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :to="{ name: 'journal', query: { date: report.date } }"
                                    icon-end="arrow-right"
                                    >Open</Button
                                >
                            </CardHeader>
                            <Text v-if="report.journal" class="break-words whitespace-pre-line">{{
                                report.journal
                            }}</Text>
                            <Text v-else role="label" tone="muted">No journal entry for this day.</Text>
                        </Card>
                    </div>
                </template>
            </Tabs>
        </template>
    </div>
</template>
