<script setup lang="ts">
import type { Task } from '@focus/types';
import { groupByDue } from '@/features/tasks/dueGroups';
import { useTaskEditing } from '@/features/tasks/useTaskEditing';
import { formatLongDate, todayIso } from '@/lib/dates';
import { dayProgress } from '@/lib/progress';

const dashboard = useDashboard();
const goals = useGoalsStore();
const outbox = useOutboxStore();
const refresh = () => {
    goals.reset();
    return dashboard.invalidate();
};
const { actions, dialogOpen, current, defaults, deleting, deletingBusy, create, confirmDelete } =
    useTaskEditing(refresh);
const blockerDialog = ref(false);
const completing = ref<Task | null>(null);
const completeOpen = ref(false);
const moving = ref(false);

function completeOn(task: Task): void {
    completing.value = task;
    completeOpen.value = true;
}

async function moveAllOverdue(): Promise<void> {
    moving.value = true;
    await actions.moveToToday(dashboard.data?.overdue_tasks ?? []);
    moving.value = false;
}

const day = computed(() => (dashboard.data ? dayProgress(dashboard.data.stats) : null));
const settings = useSettingsStore();
let carried = false;
watch(
    () => [dashboard.data?.overdue_tasks, settings.values.tasks.carry_over] as const,
    ([overdue]) => {
        if (!overdue?.length || !settings.values.tasks.carry_over || carried) return;
        carried = true;
        void actions.moveToToday(overdue);
    },
    { immediate: true },
);

const plannerOn = computed(() => settings.values.planner.enabled);
const planGroups = computed(() => {
    const groups = groupByDue(
        outbox.withPending(
            dashboard.data?.planned_tasks ?? [],
            (task) => task.is_highlighted || (!!task.due_date && task.due_date <= todayIso()),
        ),
    );
    const of = (...keys: string[]) =>
        groups.filter((group) => keys.includes(group.key)).flatMap((group) => group.tasks);
    return [
        {
            key: 'today',
            title: 'Due today',
            icon: 'calendar-check' as const,
            tone: 'default' as const,
            tasks: of('today'),
        },
        { key: 'overdue', title: 'Overdue', icon: 'warning' as const, tone: 'danger' as const, tasks: of('overdue') },
        {
            key: 'starred',
            title: 'Starred for today',
            icon: 'star' as const,
            tone: 'default' as const,
            tasks: of('week', 'later', 'none'),
        },
    ].filter((group) => group.tasks.length);
});
const overdue = computed(() => outbox.withPending(dashboard.data?.overdue_tasks ?? [], () => false));
const planSummary = computed(() =>
    planGroups.value.map((group) => `${group.tasks.length} ${group.title.toLowerCase()}`).join(' · '),
);
const plannable = computed<Task[]>(() => {
    const data = dashboard.data;
    if (!data) return [];
    const seen = new Set<number>();
    return [...data.planned_tasks, ...data.overdue_tasks, ...data.today_tasks].filter(
        (task) => task.status !== 'completed' && task.status !== 'cancelled' && !seen.has(task.id) && seen.add(task.id),
    );
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
                    label="Completed today"
                    icon="check-circle"
                    :value="dashboard.data.stats.completed_today"
                    :hint="day.total ? `${day.done} of ${day.total} in today’s plan` : 'Nothing planned'"
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
                    <span v-if="day.total" class="font-display text-title font-semibold tabular"
                        >{{ day.percent }}%</span
                    >
                    <Text v-else role="label" tone="muted"
                        >Star a task or give it today’s due date to plan your day</Text
                    >
                </div>
                <PlanProgress v-if="day.total" :day="day" />
            </Card>

            <div class="grid gap-6" :class="plannerOn ? 'xl:grid-cols-3' : ''">
                <div class="grid gap-6" :class="plannerOn ? 'content-start xl:col-span-2' : 'xl:grid-cols-2'">
                    <Card as="section" aria-labelledby="plan-heading" class="group">
                        <CardHeader
                            title="Today’s plan"
                            title-id="plan-heading"
                            icon="calendar-check"
                            :subtitle="planSummary || `${day.remaining} remaining`"
                        />
                        <div v-if="planGroups.length" class="flex flex-col gap-6">
                            <section
                                v-for="group in planGroups"
                                :key="group.key"
                                :aria-labelledby="`plan-${group.key}`"
                                class="flex flex-col gap-1"
                            >
                                <div class="flex items-center gap-2 border-b border-border pb-2">
                                    <Icon
                                        :name="group.icon"
                                        size="sm"
                                        :class="group.tone === 'danger' ? 'text-danger-fg' : 'text-highlight-fg'"
                                    />
                                    <Text :id="`plan-${group.key}`" as="h3" role="label" weight="semibold" uppercase>{{
                                        group.title
                                    }}</Text>
                                    <Badge :tone="group.tone === 'danger' ? 'danger' : 'neutral'" class="ml-auto">{{
                                        group.tasks.length
                                    }}</Badge>
                                </div>
                                <ul class="-mx-2 flex flex-col">
                                    <TaskFolderList
                                        :tasks="group.tasks"
                                        @toggle="actions.toggleComplete"
                                        @star="actions.toggleHighlight"
                                    />
                                </ul>
                            </section>
                        </div>
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
                        >
                            <Button
                                v-if="overdue.length"
                                variant="outline"
                                size="sm"
                                icon-start="calendar-check"
                                :loading="moving"
                                @click="moveAllOverdue"
                                >Move all to today</Button
                            >
                        </CardHeader>
                        <ul v-if="overdue.length" class="-mx-2 flex flex-col">
                            <TaskFolderList
                                :tasks="overdue"
                                @toggle="actions.toggleComplete"
                                @star="actions.toggleHighlight"
                            >
                                <template #actions="{ task }">
                                    <IconButton
                                        icon="history"
                                        :label="`Mark “${task.title}” done on an earlier day`"
                                        size="sm"
                                        variant="ghost"
                                        @click="completeOn(task)"
                                    />
                                    <IconButton
                                        icon="arrow-right"
                                        :label="`Move “${task.title}” to today`"
                                        size="sm"
                                        variant="ghost"
                                        @click="actions.moveToToday([task])"
                                    />
                                </template>
                            </TaskFolderList>
                        </ul>
                        <Text v-else role="label" tone="muted" class="py-4">You’re all caught up.</Text>
                    </Card>
                </div>
                <DayPlanner v-if="plannerOn" :date="dashboard.data.date" :tasks="plannable" @changed="refresh" />
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
        <CompleteOnDialog v-model:open="completeOpen" :task="completing" @saved="refresh" />
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
