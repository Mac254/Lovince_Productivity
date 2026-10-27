<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { Blocker, Task, TaskStatus } from '@focus/types';
import {
    Badge,
    Button,
    Card,
    CardHeader,
    ConfirmDialog,
    ErrorState,
    IconButton,
    PageHeader,
    Select,
    Skeleton,
    Text,
    useToast,
} from '@focus/ui';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ToneDot from '@/components/ToneDot.vue';
import BlockerFormDialog from '@/features/blockers/BlockerFormDialog.vue';
import BlockerList from '@/features/blockers/BlockerList.vue';
import PriorityDots from '@/features/tasks/PriorityDots.vue';
import StatusBadge from '@/features/tasks/StatusBadge.vue';
import TaskTypeBadge from '@/features/tasks/TaskTypeBadge.vue';
import TaskWorkLog from '@/features/tasks/TaskWorkLog.vue';
import TaskFormDialog from '@/features/tasks/TaskFormDialog.vue';
import TaskRow from '@/features/tasks/TaskRow.vue';
import { useTaskActions } from '@/features/tasks/useTaskActions';
import { dueLabel, formatDate } from '@/lib/dates';
import { taskStatusOptions } from '@/lib/labels';
import { useDashboardStore } from '@/stores/dashboard';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const dashboard = useDashboardStore();

const task = ref<Task | null>(null);
const blockers = ref<Blocker[]>([]);
const related = ref<Task[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const editOpen = ref(false);
const blockerOpen = ref(false);
const deleting = ref(false);
const deleteBusy = ref(false);

const id = computed(() => Number(route.params.id));

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const loaded = await api.tasks.get(id.value);
        task.value = loaded;
        const [taskBlockers, goalTasks] = await Promise.all([
            api.blockers.list({ task_id: loaded.id }),
            loaded.goal_id ? api.tasks.list({ goal_id: loaded.goal_id, per_page: 12 }) : Promise.resolve(null),
        ]);
        blockers.value = taskBlockers;
        related.value = goalTasks?.data.filter((t) => t.id !== loaded.id) ?? [];
    } catch (e) {
        error.value = errorMessage(e, 'Could not load this task.');
    } finally {
        loading.value = false;
    }
}

const changed = () => {
    void dashboard.load();
    return load();
};
const actions = useTaskActions(changed);

async function setStatus(status: TaskStatus | '' | null): Promise<void> {
    if (task.value && status && status !== task.value.status) await actions.setStatus(task.value, status);
}

async function confirmDelete(): Promise<void> {
    if (!task.value) return;
    deleteBusy.value = true;
    try {
        await api.tasks.remove(task.value.id);
        toast.success('Task deleted');
        void dashboard.load();
        await router.replace({ name: 'tasks' });
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the task.'));
    } finally {
        deleteBusy.value = false;
    }
}

const details = computed(() => {
    const t = task.value;
    if (!t) return [];
    return [
        { label: 'Start date', value: t.start_date ? formatDate(t.start_date, { dateStyle: 'medium' }) : 'Not set' },
        {
            label: 'Due date',
            value: t.due_date
                ? `${formatDate(t.due_date, { dateStyle: 'medium' })} · ${dueLabel(t.due_date)}`
                : 'Not set',
        },
        {
            label: 'Completed',
            value: t.completed_at ? formatDate(t.completed_at, { dateStyle: 'medium', timeStyle: 'short' }) : '—',
        },
        { label: 'Created', value: formatDate(t.created_at, { dateStyle: 'medium', timeStyle: 'short' }) },
        { label: 'Last updated', value: formatDate(t.updated_at, { dateStyle: 'medium', timeStyle: 'short' }) },
    ];
});

watch(id, load, { immediate: true });
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            :title="task?.title ?? 'Task'"
            title-id="page-title"
            :back="{ to: { name: 'tasks' }, label: 'Back to all tasks' }"
        >
            <template v-if="task" #eyebrow>
                <StatusBadge :status="task.status" />
                <TaskTypeBadge :task="task" />
                <PriorityDots :priority="task.priority" />
                <Badge v-if="task.is_overdue" tone="danger" icon="alert-circle">Overdue</Badge>
            </template>
            <template v-if="task" #title>
                <span :class="task.status === 'completed' && 'line-through'">{{ task.title }}</span>
            </template>
            <template v-if="task" #actions>
                <IconButton
                    :icon="task.is_highlighted ? 'star-filled' : 'star'"
                    :label="task.is_highlighted ? 'Remove from highlights' : 'Highlight task'"
                    :pressed="task.is_highlighted"
                    @click="actions.toggleHighlight(task)"
                />
                <Button variant="outline" icon-start="edit" @click="editOpen = true">Edit</Button>
                <Button variant="outline" icon-start="trash" @click="deleting = true">Delete</Button>
                <Button
                    :icon-start="task.status === 'completed' ? 'undo' : 'check'"
                    @click="actions.toggleComplete(task)"
                >
                    {{ task.status === 'completed' ? 'Reopen' : 'Mark complete' }}
                </Button>
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="load" />
        <div v-else-if="loading && !task" class="grid gap-6 lg:grid-cols-3" aria-busy="true">
            <Skeleton class="h-64 lg:col-span-2" />
            <Skeleton class="h-64" />
        </div>

        <template v-else-if="task">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="flex flex-col gap-6 lg:col-span-2">
                    <Card as="section" aria-labelledby="description-heading" class="group">
                        <CardHeader title="Description" title-id="description-heading" icon="journal" />
                        <Text v-if="task.description" class="break-words whitespace-pre-line">{{
                            task.description
                        }}</Text>
                        <Text v-else tone="muted"
                            >No description yet.
                            <button
                                type="button"
                                class="font-semibold text-fg underline underline-offset-4"
                                @click="editOpen = true"
                            >
                                Add one
                            </button></Text
                        >
                    </Card>

                    <TaskWorkLog :task-id="task.id" :task-title="task.title" />

                    <Card as="section" aria-labelledby="blockers-heading" class="group">
                        <CardHeader
                            title="Blockers"
                            title-id="blockers-heading"
                            icon="blocker"
                            :subtitle="`${blockers.filter((b) => b.status === 'open').length} open`"
                        >
                            <Button variant="outline" size="sm" icon-start="add" @click="blockerOpen = true"
                                >Log blocker</Button
                            >
                        </CardHeader>
                        <BlockerList v-if="blockers.length" :blockers="blockers" @changed="changed" />
                        <Text v-else role="label" tone="muted" class="py-2">Nothing is blocking this task.</Text>
                    </Card>

                    <Card v-if="task.goal" as="section" aria-labelledby="related-heading" class="group">
                        <CardHeader :title="`More in ${task.goal.title}`" title-id="related-heading" icon="target" />
                        <ul v-if="related.length" class="-mx-2 flex flex-col">
                            <TaskRow
                                v-for="item in related"
                                :key="item.id"
                                :task="item"
                                @toggle="actions.toggleComplete"
                                @star="actions.toggleHighlight"
                            />
                        </ul>
                        <Text v-else role="label" tone="muted" class="py-2">This is the only task in the goal.</Text>
                    </Card>
                </div>

                <Card as="aside" aria-labelledby="details-heading" class="group h-fit gap-4">
                    <CardHeader title="Details" title-id="details-heading" icon="info" />
                    <Select
                        id="task-status-quick"
                        :model-value="task.status"
                        label="Status"
                        :options="taskStatusOptions"
                        @update:model-value="setStatus"
                    />
                    <div v-if="task.goal" class="flex flex-col gap-1">
                        <p class="text-label font-semibold">Goal</p>
                        <RouterLink
                            :to="{ name: 'goal-detail', params: { id: task.goal.id } }"
                            class="inline-flex items-center gap-2 font-semibold underline-offset-4 hover:underline"
                        >
                            <ToneDot v-if="task.goal.tone" :tone="task.goal.tone" />
                            {{ task.goal.title }}
                        </RouterLink>
                    </div>
                    <dl class="flex flex-col divide-y divide-border">
                        <div v-for="item in details" :key="item.label" class="flex justify-between gap-4 py-2">
                            <dt class="text-label text-fg-muted">{{ item.label }}</dt>
                            <dd class="text-right text-label font-semibold tabular">{{ item.value }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>
        </template>

        <TaskFormDialog v-model:open="editOpen" :task="task" @saved="changed" />
        <BlockerFormDialog v-model:open="blockerOpen" :task="task" @saved="changed" />
        <ConfirmDialog
            v-model:open="deleting"
            title="Delete task?"
            :message="`“${task?.title}” and its blockers will be permanently deleted.`"
            :loading="deleteBusy"
            @confirm="confirmDelete"
        />
    </div>
</template>
