<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import { useStaggerIn } from '@focus/motion';
import type { Goal, Task } from '@focus/types';
import {
    Button,
    Card,
    CardHeader,
    ChipGroup,
    ConfirmDialog,
    EmptyState,
    ErrorState,
    PageHeader,
    ProgressBar,
    Skeleton,
    Text,
    useToast,
} from '@focus/ui';
import { computed, ref } from 'vue';
import BlockerList from '@/features/blockers/BlockerList.vue';
import StatCard from '@/features/dashboard/StatCard.vue';
import TaskListPanel from '@/features/dashboard/TaskListPanel.vue';
import GoalCard from '@/features/goals/GoalCard.vue';
import GoalFormDialog from '@/features/goals/GoalFormDialog.vue';
import QuoteCard from '@/features/quotes/QuoteCard.vue';
import TaskFormDialog from '@/features/tasks/TaskFormDialog.vue';
import TaskRow from '@/features/tasks/TaskRow.vue';
import TypeBreakdownList from '@/features/tasks/TypeBreakdownList.vue';
import { useTaskEditing } from '@/features/tasks/useTaskEditing';
import { taskPriorityRank } from '@/lib/labels';
import { useDashboardStore } from '@/stores/dashboard';
import { useGoalsStore } from '@/stores/goals';

const dashboard = useDashboardStore();
const goalsStore = useGoalsStore();

const refresh = () => {
    goalsStore.reset();
    return dashboard.load();
};
const { actions, dialogOpen, current, defaults, deleting, deletingBusy, create, confirmDelete } =
    useTaskEditing(refresh);
const toast = useToast();
const goalDialog = ref(false);
const editingGoal = ref<Goal | null>(null);
const deletingGoal = ref<Goal | null>(null);
const deletingGoalBusy = ref(false);

function editGoal(goal: Goal | null): void {
    editingGoal.value = goal;
    goalDialog.value = true;
}

async function confirmDeleteGoal(): Promise<void> {
    if (!deletingGoal.value) return;
    deletingGoalBusy.value = true;
    try {
        await api.goals.remove(deletingGoal.value.id);
        toast.success('Goal deleted');
        deletingGoal.value = null;
        await refresh();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the goal.'));
    } finally {
        deletingGoalBusy.value = false;
    }
}

type Focus = 'all' | 'overdue' | 'high' | 'in_progress';
const focus = ref<Focus>('all');
const focusOptions: { value: Focus; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'high', label: 'High priority' },
    { value: 'in_progress', label: 'In progress' },
];

const data = computed(() => dashboard.data);
const stats = computed(() => dashboard.data?.stats);

const focusTasks = computed<Task[]>(() => {
    if (!data.value) return [];
    const seen = new Set<number>();
    return [...data.value.overdue_tasks, ...data.value.planned_tasks]
        .filter((task) => !seen.has(task.id) && !!seen.add(task.id))
        .sort(
            (a, b) =>
                Number(b.is_overdue) - Number(a.is_overdue) ||
                taskPriorityRank[b.priority] - taskPriorityRank[a.priority],
        )
        .filter((task) => {
            if (focus.value === 'overdue') return task.is_overdue;
            if (focus.value === 'high') return taskPriorityRank[task.priority] >= 3;
            if (focus.value === 'in_progress') return task.status === 'in_progress';
            return true;
        });
});

const day = computed(() => {
    if (!stats.value) return null;
    const done = stats.value.completed_today;
    const total = done + stats.value.planned_today;
    return { done, total, percent: total ? Math.round((done / total) * 100) : 0 };
});

const content = ref<HTMLElement | null>(null);
useStaggerIn(content, { selector: '[data-stagger], article', when: () => dashboard.data });
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Dashboard"
            title-id="page-title"
            description="What needs your attention today, and how the week is going."
        >
            <template #actions>
                <Button variant="outline" icon-start="target" @click="editGoal(null)">New goal</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="dashboard.error && !data" :message="dashboard.error" @retry="dashboard.load()" />

        <div v-if="data && stats && day" ref="content" class="flex flex-col gap-6">
            <section aria-label="Key numbers" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    data-stagger
                    label="Open tasks"
                    icon="tasks"
                    motion="nudge"
                    :value="stats.open"
                    :hint="`${stats.in_progress} in progress`"
                />
                <StatCard
                    data-stagger
                    label="Done this week"
                    icon="check-circle"
                    :value="stats.completed_this_week"
                    :hint="`+${stats.completed_today} today`"
                    emphasis
                />
                <StatCard
                    data-stagger
                    label="Due this week done"
                    icon="calendar-check"
                    motion="lift"
                    :value="stats.weekly_completion_rate"
                    suffix="%"
                    :hint="`${stats.week_due_completed} of ${stats.week_due_total} tasks`"
                />
                <StatCard
                    data-stagger
                    label="Overdue · blockers"
                    icon="blocker"
                    motion="wiggle"
                    :value="stats.overdue"
                    :hint="`${stats.open_blockers} open ${stats.open_blockers === 1 ? 'blocker' : 'blockers'}`"
                />
            </section>

            <div class="grid gap-6 xl:grid-cols-3">
                <Card as="section" data-stagger aria-labelledby="today-heading" class="group xl:col-span-2">
                    <CardHeader
                        title="Today’s plan"
                        title-id="today-heading"
                        icon="calendar-check"
                        :subtitle="`${stats.planned_today} planned · ${stats.overdue} overdue`"
                    >
                        <Button variant="ghost" size="sm" icon-start="add" @click="create()">Add</Button>
                    </CardHeader>
                    <ChipGroup v-model="focus" label="Filter today’s tasks" :options="focusOptions" class="mb-3" />
                    <ul v-if="focusTasks.length" class="-mx-2 flex flex-col">
                        <TaskRow
                            v-for="task in focusTasks"
                            :key="task.id"
                            :task="task"
                            @toggle="actions.toggleComplete"
                            @star="actions.toggleHighlight"
                        />
                    </ul>
                    <EmptyState
                        v-else
                        icon="checklist"
                        :title="focus === 'all' ? 'Nothing planned for today' : 'No tasks match this filter'"
                        :message="
                            focus === 'all' ? 'Star a task or give it today’s due date to plan your day.' : undefined
                        "
                    />
                </Card>

                <div class="flex flex-col gap-6">
                    <Card as="section" data-stagger aria-labelledby="progress-heading" class="group gap-3">
                        <CardHeader title="Today’s progress" title-id="progress-heading" icon="flame" />
                        <div class="flex items-baseline justify-between">
                            <span class="font-display text-display-sm font-semibold tabular">{{ day.percent }}%</span>
                            <Text role="label" tone="muted" tabular>{{ day.done }} of {{ day.total }} done</Text>
                        </div>
                        <ProgressBar :value="day.percent" label="Today’s progress" tone="highlight" />
                        <Text v-if="data.planned_tasks[0]" role="label" tone="muted" truncate
                            >Next: {{ data.planned_tasks[0].title }}</Text
                        >
                    </Card>
                    <QuoteCard data-stagger :initial="data.quote" />
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <Card as="section" data-stagger aria-labelledby="highlights-heading" class="group">
                    <CardHeader
                        title="Highlights"
                        title-id="highlights-heading"
                        icon="star"
                        subtitle="Tasks you starred"
                    />
                    <ul v-if="data.highlighted_tasks.length" class="-mx-2 flex flex-col">
                        <TaskRow
                            v-for="task in data.highlighted_tasks"
                            :key="task.id"
                            :task="task"
                            @toggle="actions.toggleComplete"
                            @star="actions.toggleHighlight"
                        />
                    </ul>
                    <Text v-else role="label" tone="muted" class="py-4">Star a task to keep it here.</Text>
                </Card>
                <TaskListPanel
                    title="Upcoming deadlines"
                    icon="calendar-clock"
                    :tasks="data.upcoming_tasks"
                    empty="No deadlines in the next two weeks."
                />
                <Card as="section" data-stagger aria-labelledby="blockers-heading" class="group">
                    <CardHeader title="Open blockers" title-id="blockers-heading" icon="blocker">
                        <Button variant="ghost" size="sm" :to="{ name: 'blockers' }" icon-end="arrow-right">All</Button>
                    </CardHeader>
                    <BlockerList
                        v-if="data.open_blockers.length"
                        :blockers="data.open_blockers"
                        show-task
                        @changed="refresh"
                    />
                    <Text v-else role="label" tone="muted" class="py-4">Nothing is blocking you.</Text>
                </Card>
            </div>

            <section aria-labelledby="goals-heading" class="flex flex-col gap-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="goals-heading" class="font-display text-title font-semibold">Active goals</h2>
                    <Button variant="ghost" size="sm" :to="{ name: 'goals' }" icon-end="arrow-right">All goals</Button>
                </div>
                <div v-if="data.active_goals.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5">
                    <GoalCard
                        v-for="goal in data.active_goals"
                        :key="goal.id"
                        :goal="goal"
                        @edit="editGoal"
                        @delete="deletingGoal = $event"
                    />
                </div>
                <EmptyState
                    v-else
                    icon="target"
                    title="No active goals"
                    message="Group related tasks under a goal to see measurable progress."
                />
            </section>

            <div class="grid gap-6 lg:grid-cols-3">
                <Card as="section" data-stagger aria-labelledby="types-heading" class="group">
                    <CardHeader
                        title="By type"
                        title-id="types-heading"
                        icon="tag"
                        subtitle="Open now · done this week"
                    >
                        <Button variant="ghost" size="sm" :to="{ name: 'types' }" icon-end="arrow-right">Types</Button>
                    </CardHeader>
                    <TypeBreakdownList
                        v-if="data.by_type.length"
                        :rows="data.by_type"
                        open-label="open"
                        completed-label="done"
                    />
                    <Text v-else role="label" tone="muted" class="py-4">No open or recent work yet.</Text>
                </Card>
                <TaskListPanel
                    title="Integration"
                    icon="integration"
                    :tasks="data.integration.integration_pending"
                    empty="No integration work pending."
                >
                    <template #lead>
                        <dl class="mb-4 grid grid-cols-3 gap-2 text-center">
                            <div class="flex flex-col gap-1 rounded-md bg-surface-raised px-2 py-3">
                                <dd class="font-display text-heading font-semibold tabular">
                                    {{ data.integration.development_completed }}
                                </dd>
                                <Text as="dt" role="caption" tone="muted">Dev done</Text>
                            </div>
                            <div class="flex flex-col gap-1 rounded-md bg-highlight px-2 py-3 text-highlight-ink">
                                <dd class="font-display text-heading font-semibold tabular">
                                    {{ data.integration.integration_pending.length }}
                                </dd>
                                <dt class="text-caption">To integrate</dt>
                            </div>
                            <div class="flex flex-col gap-1 rounded-md bg-surface-raised px-2 py-3">
                                <dd class="font-display text-heading font-semibold tabular">
                                    {{ data.integration.integration_completed }}
                                </dd>
                                <Text as="dt" role="caption" tone="muted">Integrated</Text>
                            </div>
                        </dl>
                    </template>
                </TaskListPanel>
                <TaskListPanel
                    title="Recent activity"
                    icon="history"
                    :tasks="data.recent_activity"
                    mode="activity"
                    empty="Activity will appear here as you work."
                />
            </div>
        </div>

        <div
            v-else-if="dashboard.loading"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            aria-busy="true"
            aria-label="Loading dashboard"
        >
            <Skeleton v-for="n in 8" :key="n" class="h-36" />
        </div>

        <TaskFormDialog v-model:open="dialogOpen" :task="current" :defaults="defaults" @saved="refresh" />
        <GoalFormDialog v-model:open="goalDialog" :goal="editingGoal" @saved="refresh" />
        <ConfirmDialog
            :open="!!deletingGoal"
            title="Delete goal?"
            :message="`“${deletingGoal?.title}” will be deleted. Its tasks are kept and simply unlinked from the goal.`"
            :loading="deletingGoalBusy"
            @update:open="!$event && (deletingGoal = null)"
            @confirm="confirmDeleteGoal"
        />
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
