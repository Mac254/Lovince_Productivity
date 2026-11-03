<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { TabItem } from '@focus/ui';
import type { Achievement, Goal, Task } from '@focus/types';
import { useTaskActions } from '@/features/tasks/useTaskActions';
import { formatDate } from '@/lib/dates';
import { goalStatusLabels, goalStatusTones, goalTimeframeLabels } from '@/lib/labels';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const dashboard = useDashboardStore();
const goalsStore = useGoalsStore();

const goal = ref<Goal | null>(null);
const tasks = ref<Task[]>([]);
const achievements = ref<Achievement[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const editOpen = ref(false);
const taskOpen = ref(false);
const deleting = ref(false);
const deleteBusy = ref(false);
const achievementOpen = ref(false);
const editingAchievement = ref<Achievement | null>(null);

const id = computed(() => Number(route.params.id));

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const [loadedGoal, goalTasks, goalAchievements] = await Promise.all([
            api.goals.get(id.value),
            api.tasks.list({ goal_id: id.value, per_page: 100 }),
            api.achievements.list({ goal_id: id.value }),
        ]);
        goal.value = loadedGoal;
        tasks.value = goalTasks.data;
        achievements.value = goalAchievements;
    } catch (e) {
        error.value = errorMessage(e, 'Could not load this goal.');
    } finally {
        loading.value = false;
    }
}

const changed = () => {
    goalsStore.reset();
    void dashboard.invalidate();
    return load();
};
const actions = useTaskActions(changed);

function goalSaved(saved: Goal): void {
    const completedNow = saved.status === 'completed' && goal.value?.status !== 'completed';
    goal.value = saved;
    void changed();
    if (completedNow) recordAchievement();
}

function recordAchievement(): void {
    editingAchievement.value = null;
    achievementOpen.value = true;
}

function editAchievement(achievement: Achievement): void {
    editingAchievement.value = achievement;
    achievementOpen.value = true;
}

async function achievementsChanged(): Promise<void> {
    achievements.value = await api.achievements.list({ goal_id: id.value }).catch(() => achievements.value);
}

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
const tab = useTabQuery(['open', 'done', 'cancelled'] as const, 'open');
const tabs = computed<TabItem<typeof tab.value>[]>(() =>
    groups.value.map((group) => ({
        value: group.key as typeof tab.value,
        label: group.title,
        icon: group.icon,
        count: group.items.length,
    })),
);
const tabTasks = usePagedList(() => groups.value.find((group) => group.key === tab.value)?.items ?? [], 10);
watch(tab, () => (tabTasks.page = 1));
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
        <PageHeader
            :title="goal?.title ?? 'Goal'"
            title-id="page-title"
            :back="{ to: { name: 'goals' }, label: 'Back to all goals' }"
        >
            <template v-if="goal" #eyebrow>
                <Badge tone="inverse"><ToneDot :tone="goal.tone" />{{ goalTimeframeLabels[goal.timeframe] }}</Badge>
                <Badge dot :tone="goalStatusTones[goal.status]">{{ goalStatusLabels[goal.status] }}</Badge>
            </template>
            <template v-if="goal?.description" #description>
                <span class="block max-w-dialog whitespace-pre-line">{{ goal.description }}</span>
            </template>
            <template v-if="goal" #actions>
                <Button variant="outline" icon-start="edit" @click="editOpen = true">Edit</Button>
                <Button variant="outline" icon-start="trash" @click="deleting = true">Delete</Button>
                <Button icon-start="add" @click="taskOpen = true">Add task</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="load" />
        <div v-else-if="loading && !goal" class="grid gap-4 sm:grid-cols-4" aria-busy="true">
            <Skeleton v-for="n in 4" :key="n" class="h-36" />
        </div>

        <template v-else-if="goal">
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

            <Alert v-if="goal.status === 'completed' && !achievements.length" icon="star">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>You completed this goal. Record it as an achievement to look back on.</span>
                    <Button size="sm" icon-start="star" @click="recordAchievement">Record an achievement</Button>
                </div>
            </Alert>

            <Tabs v-model="tab" label="Goal tasks" :tabs="tabs">
                <template v-for="group in groups" :key="group.key" #[group.key]>
                    <Card as="div" class="gap-3">
                        <ul v-if="group.items.length" class="-mx-2 flex flex-col">
                            <TaskRow
                                v-for="task in tabTasks.visible"
                                :key="task.id"
                                :task="task"
                                @toggle="actions.toggleComplete"
                                @star="actions.toggleHighlight"
                            />
                        </ul>
                        <Text v-else role="label" tone="muted" class="py-2"
                            >No {{ group.title.toLowerCase() }} tasks.</Text
                        >
                        <Pagination v-model:page="tabTasks.page" :last-page="tabTasks.lastPage" />
                    </Card>
                </template>
            </Tabs>

            <section aria-labelledby="goal-achievements" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="goal-achievements" class="font-display text-title font-semibold tracking-tight">
                        Achievements
                    </h2>
                    <Button variant="outline" icon-start="star" @click="recordAchievement">Record achievement</Button>
                </div>
                <AchievementTimeline
                    v-if="achievements.length"
                    :achievements="achievements"
                    @edit="editAchievement"
                    @changed="achievementsChanged"
                />
                <Text v-else role="label" tone="muted">
                    Milestones along the way — a finished chapter, a first run — show up here.
                </Text>
            </section>
        </template>

        <GoalFormDialog v-model:open="editOpen" :goal="goal" @saved="goalSaved" />
        <AchievementFormDialog
            v-model:open="achievementOpen"
            :achievement="editingAchievement"
            :defaults="{ goal_id: goal?.id, title: goal?.status === 'completed' ? goal.title : '' }"
            @saved="achievementsChanged"
        />
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
