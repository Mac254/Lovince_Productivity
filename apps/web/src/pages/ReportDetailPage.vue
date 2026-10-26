<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { DailyReport } from '@focus/types';
import {
    Badge,
    Button,
    Card,
    CardHeader,
    ErrorState,
    PageHeader,
    ProgressBar,
    Skeleton,
    Text,
    useToast,
} from '@focus/ui';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import StatCard from '@/features/dashboard/StatCard.vue';
import TaskTypeBadge from '@/features/tasks/TaskTypeBadge.vue';
import TypeBreakdownList from '@/features/tasks/TypeBreakdownList.vue';
import { dueLabel, formatDate, todayIso } from '@/lib/dates';
import { taskPriorityLabels, taskPriorityTones } from '@/lib/labels';

const route = useRoute();
const toast = useToast();
const report = ref<DailyReport | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const regenerating = ref(false);

const date = computed(() => String(route.params.date));
const isToday = computed(() => date.value === todayIso());

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
                <Text role="caption" tone="muted"
                    >Generated {{ formatDate(report.generated_at, { timeStyle: 'short' }) }}</Text
                >
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
                    :hint="`${report.summary.high_priority_completed} high priority`"
                    emphasis
                />
                <StatCard
                    label="Due today done"
                    icon="calendar-check"
                    :value="report.summary.completion_rate"
                    suffix="%"
                    :hint="`${report.summary.due_completed} of ${report.summary.due}`"
                />
                <StatCard
                    v-if="report.summary.planned !== undefined"
                    label="Plan done"
                    icon="checklist"
                    :value="report.summary.planned_completed ?? 0"
                    :hint="`of ${report.summary.planned} planned · ${report.summary.created} added`"
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

            <div class="grid gap-6 lg:grid-cols-2">
                <Card as="section" aria-labelledby="done-heading" class="group">
                    <CardHeader
                        title="Completed"
                        title-id="done-heading"
                        icon="check-circle"
                        :subtitle="`${report.completed_tasks.length} tasks`"
                    />
                    <ul v-if="report.completed_tasks.length" class="flex flex-col divide-y divide-border">
                        <li
                            v-for="task in report.completed_tasks"
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
                </Card>

                <Card as="section" aria-labelledby="carried-heading" class="group">
                    <CardHeader
                        title="Carried over"
                        title-id="carried-heading"
                        icon="history"
                        subtitle="Due this day, still open when the report was made"
                    />
                    <ul v-if="report.carried_over.length" class="flex flex-col divide-y divide-border">
                        <li
                            v-for="task in report.carried_over"
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
                </Card>

                <Card as="section" aria-labelledby="overdue-heading" class="group">
                    <CardHeader
                        title="Overdue"
                        title-id="overdue-heading"
                        icon="warning"
                        :subtitle="`${report.summary.overdue} tasks`"
                    />
                    <ul v-if="report.overdue_tasks.length" class="flex flex-col divide-y divide-border">
                        <li
                            v-for="task in report.overdue_tasks"
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
                </Card>

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
                            <Text v-if="blocker.task" role="caption" tone="muted">{{ blocker.task.title }}</Text>
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

                <Card as="section" aria-labelledby="types-heading" class="group">
                    <CardHeader
                        title="Done by type"
                        title-id="types-heading"
                        icon="tag"
                        :subtitle="`${report.summary.completed} completed`"
                    />
                    <TypeBreakdownList v-if="report.by_type?.length" :rows="report.by_type" completed-label="done" />
                    <Text v-else role="label" tone="muted">Nothing was completed this day.</Text>
                </Card>

                <Card as="section" aria-labelledby="goals-heading" class="group">
                    <CardHeader
                        title="Goals"
                        title-id="goals-heading"
                        icon="target"
                        subtitle="Progress at the time of the report"
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
                    <Text v-if="report.journal" class="break-words whitespace-pre-line">{{ report.journal }}</Text>
                    <Text v-else role="label" tone="muted">No journal entry for this day.</Text>
                </Card>
            </div>
        </template>
    </div>
</template>
