<script setup lang="ts">
import {
    Button,
    Card,
    CardHeader,
    ConfirmDialog,
    EmptyState,
    ErrorState,
    PageHeader,
    ProgressBar,
    Skeleton,
    Text,
} from '@focus/ui';
import { computed, ref } from 'vue';
import BlockerFormDialog from '@/features/blockers/BlockerFormDialog.vue';
import BlockerList from '@/features/blockers/BlockerList.vue';
import StatCard from '@/features/dashboard/StatCard.vue';
import TaskListPanel from '@/features/dashboard/TaskListPanel.vue';
import WeeklyGoalsPanel from '@/features/productivity/WeeklyGoalsPanel.vue';
import TaskFormDialog from '@/features/tasks/TaskFormDialog.vue';
import TaskRow from '@/features/tasks/TaskRow.vue';
import { useTaskEditing } from '@/features/tasks/useTaskEditing';
import { formatLongDate } from '@/lib/dates';
import { useDashboardStore } from '@/stores/dashboard';
import { useGoalsStore } from '@/stores/goals';

const dashboard = useDashboardStore();
const goals = useGoalsStore();
const refresh = () => {
    goals.reset();
    return dashboard.load();
};
const { actions, dialogOpen, current, defaults, deleting, deletingBusy, create, confirmDelete } =
    useTaskEditing(refresh);
const blockerDialog = ref(false);

const day = computed(() => {
    const data = dashboard.data;
    if (!data) return null;
    const done = data.stats.completed_today;
    const remaining = data.stats.planned_today;
    const total = done + remaining;
    return { done, remaining, total, percent: total ? Math.round((done / total) * 100) : 0 };
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Today"
            title-id="page-title"
            :description="dashboard.data ? formatLongDate(dashboard.data.date) : undefined"
        >
            <template #actions>
                <Button variant="outline" icon-start="blocker" @click="blockerDialog = true">Log blocker</Button>
                <Button icon-start="add" @click="create()">New task</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="dashboard.error && !dashboard.data" :message="dashboard.error" @retry="dashboard.load()" />

        <template v-if="dashboard.data && day">
            <section aria-label="Today in numbers" class="grid gap-4 sm:grid-cols-3">
                <StatCard label="Remaining" icon="hourglass" :value="day.remaining" hint="In today’s plan, not done" />
                <StatCard
                    label="Completed"
                    icon="check-circle"
                    :value="day.done"
                    :hint="day.total ? `${day.percent}% of today’s plan` : 'Nothing planned'"
                    emphasis
                />
                <StatCard
                    label="Overdue"
                    icon="warning"
                    motion="wiggle"
                    :value="dashboard.data.stats.overdue"
                    hint="From earlier days"
                />
            </section>

            <Card as="div" class="gap-3">
                <div class="flex flex-wrap items-baseline justify-between gap-4">
                    <p class="font-display text-lead font-medium">Today’s progress</p>
                    <Text role="label" tone="muted" tabular>
                        {{
                            day.total
                                ? `${day.done} of ${day.total} tasks in today’s plan are done`
                                : 'Star a task or give it today’s due date to plan your day'
                        }}
                    </Text>
                </div>
                <ProgressBar :value="day.percent" label="Today’s progress" tone="highlight" />
            </Card>

            <div class="grid gap-6 xl:grid-cols-2">
                <Card as="section" aria-labelledby="plan-heading" class="group">
                    <CardHeader
                        title="Today’s plan"
                        title-id="plan-heading"
                        icon="calendar-check"
                        :subtitle="`${day.remaining} remaining · due today and starred`"
                    />
                    <ul v-if="dashboard.data.planned_tasks.length" class="-mx-2 flex flex-col">
                        <TaskRow
                            v-for="task in dashboard.data.planned_tasks"
                            :key="task.id"
                            :task="task"
                            @toggle="actions.toggleComplete"
                            @star="actions.toggleHighlight"
                        />
                    </ul>
                    <EmptyState
                        v-else
                        icon="sun"
                        title="Clear for today"
                        message="Star a task to add it to today’s plan."
                    />
                </Card>
                <Card as="section" aria-labelledby="overdue-heading" class="group">
                    <CardHeader
                        title="Overdue"
                        title-id="overdue-heading"
                        icon="warning"
                        :subtitle="`${dashboard.data.stats.overdue} from earlier days`"
                    />
                    <ul v-if="dashboard.data.overdue_tasks.length" class="-mx-2 flex flex-col">
                        <TaskRow
                            v-for="task in dashboard.data.overdue_tasks"
                            :key="task.id"
                            :task="task"
                            @toggle="actions.toggleComplete"
                            @star="actions.toggleHighlight"
                        />
                    </ul>
                    <Text v-else role="label" tone="muted" class="py-4">You’re all caught up.</Text>
                </Card>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <TaskListPanel
                    title="Completed today"
                    icon="check-circle"
                    :tasks="dashboard.data.completed_today_tasks"
                    mode="activity"
                    empty="Completed tasks will show up here."
                />
                <WeeklyGoalsPanel :goals="dashboard.data.weekly_goals" />
                <Card as="section" aria-labelledby="today-blockers" class="group">
                    <CardHeader title="Open blockers" title-id="today-blockers" icon="blocker" />
                    <BlockerList
                        v-if="dashboard.data.open_blockers.length"
                        :blockers="dashboard.data.open_blockers"
                        show-task
                        @changed="refresh"
                    />
                    <Text v-else role="label" tone="muted" class="py-4">Nothing is blocking you.</Text>
                </Card>
            </div>
        </template>

        <div
            v-else-if="dashboard.loading"
            class="grid gap-4 sm:grid-cols-3"
            aria-busy="true"
            aria-label="Loading today"
        >
            <Skeleton v-for="n in 6" :key="n" class="h-36" />
        </div>

        <TaskFormDialog v-model:open="dialogOpen" :task="current" :defaults="defaults" @saved="refresh" />
        <BlockerFormDialog v-model:open="blockerDialog" @saved="refresh" />
        <ConfirmDialog
            :open="!!deleting"
            title="Delete task?"
            :message="`“${deleting?.title}” will be permanently deleted.`"
            :loading="deletingBusy"
            @update:open="!$event && (deleting = null)"
            @confirm="confirmDelete"
        />
    </div>
</template>
