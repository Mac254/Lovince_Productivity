<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { Goal, Task } from '@focus/types';
import {
    Badge,
    Button,
    Card,
    CardHeader,
    ConfirmDialog,
    ErrorState,
    Icon,
    ProgressBar,
    Skeleton,
    Text,
    useToast,
} from '@focus/ui';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ToneDot from '@/components/ToneDot.vue';
import StatCard from '@/features/dashboard/StatCard.vue';
import GoalFormDialog from '@/features/goals/GoalFormDialog.vue';
import TaskFormDialog from '@/features/tasks/TaskFormDialog.vue';
import TaskRow from '@/features/tasks/TaskRow.vue';
import { useTaskActions } from '@/features/tasks/useTaskActions';
import { formatDate } from '@/lib/dates';
import { goalStatusLabels, goalStatusTones, goalTimeframeLabels } from '@/lib/labels';
import { useDashboardStore } from '@/stores/dashboard';
import { useGoalsStore } from '@/stores/goals';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const dashboard = useDashboardStore();
const goalsStore = useGoalsStore();

const goal = ref<Goal | null>(null);
const tasks = ref<Task[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const editOpen = ref(false);
const taskOpen = ref(false);
const deleting = ref(false);
const deleteBusy = ref(false);

const id = computed(() => Number(route.params.id));

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const [loadedGoal, goalTasks] = await Promise.all([
            api.goals.get(id.value),
            api.tasks.list({ goal_id: id.value, per_page: 100 }),
        ]);
        goal.value = loadedGoal;
        tasks.value = goalTasks.data;
    } catch (e) {
        error.value = errorMessage(e, 'Could not load this goal.');
    } finally {
        loading.value = false;
    }
}

const changed = () => {
    goalsStore.reset();
    void dashboard.load();
    return load();
};
const actions = useTaskActions(changed);

const groups = computed(() => [
    {
        key: 'open',
        title: 'Open',
        icon: 'tasks' as const,
        items: tasks.value.filter((t) => ['not_started', 'in_progress', 'blocked'].includes(t.status)),
    },
    {
        key: 'done',
        title: 'Completed',
        icon: 'check-circle' as const,
        items: tasks.value.filter((t) => t.status === 'completed'),
    },
    {
        key: 'cancelled',
        title: 'Cancelled',
        icon: 'forbidden' as const,
        items: tasks.value.filter((t) => t.status === 'cancelled'),
    },
]);
const overdue = computed(() => tasks.value.filter((t) => t.is_overdue).length);

async function confirmDelete(): Promise<void> {
    if (!goal.value) return;
    deleteBusy.value = true;
    try {
        await api.goals.remove(goal.value.id);
        toast.success('Goal deleted');
        goalsStore.reset();
        await router.replace({ name: 'goals' });
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the goal.'));
    } finally {
        deleteBusy.value = false;
    }
}

watch(id, load, { immediate: true });
</script>

<template>
    <div class="flex flex-col gap-6">
        <RouterLink
            :to="{ name: 'goals' }"
            class="group inline-flex w-fit items-center gap-2 text-label font-semibold text-fg-muted hover:text-fg"
        >
            <Icon name="arrow-left" motion="nudge-back" />
            <span>All goals</span>
        </RouterLink>

        <ErrorState v-if="error" :message="error" @retry="load" />
        <div v-else-if="loading && !goal" class="grid gap-4 sm:grid-cols-4" aria-busy="true">
            <Skeleton v-for="n in 4" :key="n" class="h-36" />
        </div>

        <template v-else-if="goal">
            <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <Badge tone="inverse"
                            ><ToneDot :tone="goal.tone" />{{ goalTimeframeLabels[goal.timeframe] }}</Badge
                        >
                        <Badge dot :tone="goalStatusTones[goal.status]">{{ goalStatusLabels[goal.status] }}</Badge>
                    </div>
                    <h1
                        id="page-title"
                        class="font-display text-heading font-semibold tracking-tight sm:text-display-sm"
                    >
                        {{ goal.title }}
                    </h1>
                    <Text v-if="goal.description" tone="muted" class="mt-2 max-w-dialog whitespace-pre-line">{{
                        goal.description
                    }}</Text>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" icon-start="edit" @click="editOpen = true">Edit</Button>
                    <Button variant="outline" icon-start="trash" @click="deleting = true">Delete</Button>
                    <Button icon-start="add" @click="taskOpen = true">Add task</Button>
                </div>
            </header>

            <section aria-label="Goal in numbers" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Progress"
                    icon="target"
                    motion="spin"
                    :value="goal.progress"
                    suffix="%"
                    hint="Completed ÷ non-cancelled tasks"
                    emphasis
                />
                <StatCard
                    label="Completed"
                    icon="check-circle"
                    :value="goal.completed_tasks_count"
                    :hint="`of ${goal.tasks_count} tasks`"
                />
                <StatCard
                    label="Open"
                    icon="tasks"
                    motion="nudge"
                    :value="groups[0]!.items.length"
                    hint="Not started, in progress or blocked"
                />
                <StatCard label="Overdue" icon="warning" motion="wiggle" :value="overdue" hint="Past their due date" />
            </section>

            <Card as="div" class="gap-3">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <p class="font-display text-lead font-medium">Timeline</p>
                    <Text role="label" tone="muted" tabular>
                        {{ goal.start_date ? formatDate(goal.start_date, { dateStyle: 'medium' }) : 'No start' }} →
                        {{ goal.target_date ? formatDate(goal.target_date, { dateStyle: 'medium' }) : 'No target' }}
                    </Text>
                </div>
                <ProgressBar
                    :value="goal.progress"
                    :label="`${goal.title} progress`"
                    :tone="goal.progress === 100 ? 'highlight' : 'accent'"
                />
            </Card>

            <div class="grid gap-6 lg:grid-cols-3">
                <Card
                    v-for="group in groups"
                    :key="group.key"
                    as="section"
                    :aria-labelledby="`group-${group.key}`"
                    class="group"
                >
                    <CardHeader
                        :title="group.title"
                        :title-id="`group-${group.key}`"
                        :icon="group.icon"
                        :subtitle="`${group.items.length} ${group.items.length === 1 ? 'task' : 'tasks'}`"
                    />
                    <ul v-if="group.items.length" class="-mx-2 flex flex-col">
                        <TaskRow
                            v-for="task in group.items"
                            :key="task.id"
                            :task="task"
                            @toggle="actions.toggleComplete"
                            @star="actions.toggleHighlight"
                        />
                    </ul>
                    <Text v-else role="label" tone="muted" class="py-2">None.</Text>
                </Card>
            </div>
        </template>

        <GoalFormDialog v-model:open="editOpen" :goal="goal" @saved="changed" />
        <TaskFormDialog v-model:open="taskOpen" :defaults="{ goal_id: goal?.id }" @saved="changed" />
        <ConfirmDialog
            v-model:open="deleting"
            title="Delete goal?"
            :message="`“${goal?.title}” will be deleted. Its tasks are kept and simply unlinked from the goal.`"
            :loading="deleteBusy"
            @confirm="confirmDelete"
        />
    </div>
</template>
