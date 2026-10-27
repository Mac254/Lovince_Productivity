<script setup lang="ts">
import { api } from '@focus/api-client';
import {
    LIMITS,
    TASK_CATEGORIES,
    TASK_DUE_FILTERS,
    TASK_PRIORITIES,
    TASK_SORTS,
    type TaskCategory,
    type TaskDueFilter,
    type TaskFilters,
    type TaskPriority,
    type TaskSort,
    type TaskStatus,
} from '@focus/types';
import {
    Button,
    ChipGroup,
    ConfirmDialog,
    EmptyState,
    ErrorState,
    PageHeader,
    Pagination,
    SearchField,
    Select,
    Skeleton,
    Text,
} from '@focus/ui';
import { computed, onMounted, reactive, watch } from 'vue';
import { useRoute, useRouter, type LocationQuery } from 'vue-router';
import TaskCard from '@/features/tasks/TaskCard.vue';
import TaskFormDialog from '@/features/tasks/TaskFormDialog.vue';
import { useTaskEditing } from '@/features/tasks/useTaskEditing';
import { useAsync } from '@/composables/useAsync';
import { taskPriorityOptions } from '@/lib/labels';
import { fromKind, kindOptions, type TaskKind } from '@/lib/taskKind';
import { useDashboardStore } from '@/stores/dashboard';
import { useGoalsStore } from '@/stores/goals';
import { useTaskTypesStore } from '@/stores/taskTypes';

type StatusChip = 'all' | 'open' | Exclude<TaskStatus, 'not_started'>;

const route = useRoute();
const router = useRouter();
const goals = useGoalsStore();
const taskTypes = useTaskTypesStore();
const dashboard = useDashboardStore();

function fromQuery<T extends string>(query: LocationQuery, key: string, allowed: readonly T[]): T | '' {
    const value = query[key];
    return typeof value === 'string' && (allowed as readonly string[]).includes(value) ? (value as T) : '';
}

const statusChips: { value: StatusChip; label: string }[] = [
    { value: 'open', label: 'Open' },
    { value: 'in_progress', label: 'In progress' },
    { value: 'blocked', label: 'Blocked' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'all', label: 'All' },
];

const filters = reactive({
    search: typeof route.query.search === 'string' ? route.query.search.slice(0, LIMITS.search) : '',
    status: (fromQuery(
        route.query,
        'status',
        statusChips.map((c) => c.value),
    ) || 'open') as StatusChip,
    priority: fromQuery<TaskPriority>(route.query, 'priority', TASK_PRIORITIES),
    category: fromQuery<TaskCategory>(route.query, 'category', TASK_CATEGORIES),
    due: fromQuery<TaskDueFilter>(route.query, 'due', TASK_DUE_FILTERS),
    goal_id: (Number(route.query.goal_id) || '') as number | '',
    task_type_id: (Number(route.query.task_type_id) || '') as number | '',
    sort: fromQuery<TaskSort>(route.query, 'sort', TASK_SORTS),
    page: Number(route.query.page) || 1,
});

const dueLabels: Record<TaskDueFilter, string> = {
    today: 'Due today',
    overdue: 'Overdue',
    week: 'This week',
    upcoming: 'Upcoming',
    none: 'No due date',
};
const sortLabels: Record<TaskSort, string> = {
    due_date: 'Due date',
    priority: 'Priority',
    created_at: 'Newest',
    updated_at: 'Recently updated',
    title: 'Title',
};
const dueOptions = [
    { value: '' as const, label: 'Any date' },
    ...TASK_DUE_FILTERS.map((value) => ({ value, label: dueLabels[value] })),
];
const sortOptions = [
    { value: '' as const, label: 'Smart (open first)' },
    ...TASK_SORTS.map((value) => ({ value, label: sortLabels[value] })),
];
const typeOptions = computed(() => [{ value: '' as const, label: 'Any type' }, ...kindOptions(taskTypes.types)]);
const typeFilter = computed<TaskKind | '' | null>({
    get: () => {
        if (filters.task_type_id) return `type:${filters.task_type_id}` as TaskKind;
        return filters.category ? (`category:${filters.category}` as TaskKind) : '';
    },
    set: (kind: TaskKind | '' | null) => {
        const next = kind ? fromKind(kind) : null;
        filters.task_type_id = next?.task_type_id ?? '';
        filters.category = next && !next.task_type_id ? next.category : '';
    },
});
const goalOptions = computed(() => [
    { value: '' as const, label: 'All goals' },
    ...goals.goals.map((goal) => ({ value: goal.id, label: goal.title })),
]);

const query = computed<TaskFilters>(() => ({
    search: filters.search || undefined,
    status: filters.status === 'all' ? undefined : filters.status,
    priority: filters.priority || undefined,
    category: filters.category || undefined,
    due: filters.due || undefined,
    goal_id: filters.goal_id || undefined,
    task_type_id: filters.task_type_id || undefined,
    sort: filters.sort || undefined,
    direction: filters.sort === 'created_at' || filters.sort === 'updated_at' ? 'desc' : undefined,
    page: filters.page,
    per_page: 24,
}));

const { data, loading, error, run } = useAsync((signal) => api.tasks.list(query.value, { signal }));

const hasFilters = computed(
    () =>
        !!(
            filters.search ||
            filters.priority ||
            filters.category ||
            filters.due ||
            filters.goal_id ||
            filters.task_type_id ||
            filters.status !== 'open'
        ),
);

function clearFilters(): void {
    Object.assign(filters, {
        search: '',
        status: 'open',
        priority: '',
        category: '',
        due: '',
        goal_id: '',
        task_type_id: '',
        sort: '',
        page: 1,
    });
}

let searchTimer: number | undefined;
watch(
    () => ({ ...filters }),
    (next, prev) => {
        const withoutPage = (f: typeof next) => JSON.stringify({ ...f, page: 0 });
        if (next.page === prev.page && withoutPage(next) !== withoutPage(prev)) filters.page = 1;

        const urlQuery = Object.fromEntries(
            Object.entries(filters)
                .filter(
                    ([key, value]) =>
                        value !== '' && !(key === 'page' && value === 1) && !(key === 'status' && value === 'open'),
                )
                .map(([key, value]) => [key, String(value)]),
        );
        void router.replace({ query: urlQuery });

        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(run, next.search !== prev.search ? 300 : 0);
    },
);

const refresh = async () => {
    goals.reset();
    void goals.load().catch(() => undefined);
    void taskTypes.load(true).catch(() => undefined);
    void dashboard.invalidate();
    await run();
};
const { actions, dialogOpen, current, defaults, deleting, deletingBusy, create, edit, askDelete, confirmDelete } =
    useTaskEditing(refresh);

onMounted(() => {
    void run();
    goals.load().catch(() => undefined);
    taskTypes.load().catch(() => undefined);
});
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader
            title="Tasks"
            title-id="page-title"
            description="Everything you’ve planned, filterable by status, priority, type and goal."
        >
            <template #actions>
                <Button variant="outline" icon-start="tag" :to="{ name: 'types' }">Types</Button>
                <Button
                    variant="secondary"
                    icon-start="add"
                    @click="create(filters.goal_id ? { goal_id: filters.goal_id } : {})"
                    >New task</Button
                >
            </template>
        </PageHeader>

        <section aria-label="Filters" class="flex flex-col gap-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <SearchField
                    id="task-search"
                    v-model="filters.search"
                    label="Search tasks"
                    placeholder="Search titles and descriptions"
                    :maxlength="LIMITS.search"
                    class="lg:w-80"
                />
                <ChipGroup v-model="filters.status" label="Filter by status" :options="statusChips" />
            </div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6 xl:items-end">
                <Select
                    id="filter-priority"
                    v-model="filters.priority"
                    label="Priority"
                    :options="[{ value: '', label: 'Any priority' }, ...taskPriorityOptions]"
                />
                <Select id="filter-type" v-model="typeFilter" label="Type" :options="typeOptions" />
                <Select id="filter-due" v-model="filters.due" label="Due" :options="dueOptions" />
                <Select id="filter-goal" v-model="filters.goal_id" label="Goal" :options="goalOptions" />
                <Select id="filter-sort" v-model="filters.sort" label="Sort by" :options="sortOptions" />
                <Button v-if="hasFilters" variant="ghost" icon-start="close" class="self-end" @click="clearFilters"
                    >Clear filters</Button
                >
            </div>
        </section>

        <Text role="label" tone="muted" aria-live="polite">
            <template v-if="data && !loading"
                >{{ data.meta.total }} {{ data.meta.total === 1 ? 'task' : 'tasks' }}</template
            >
            <template v-else-if="loading">Loading tasks…</template>
        </Text>

        <ErrorState v-if="error" :message="error" @retry="run" />

        <div
            v-else-if="loading && !data"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5"
            aria-hidden="true"
        >
            <Skeleton v-for="n in 8" :key="n" class="h-56" />
        </div>

        <template v-else-if="data">
            <div
                v-if="data.data.length"
                :class="[
                    'grid gap-4 transition-opacity duration-(--duration-fast) sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5',
                    loading && 'opacity-60',
                ]"
            >
                <TaskCard
                    v-for="task in data.data"
                    :key="task.id"
                    :task="task"
                    @edit="edit"
                    @toggle="actions.toggleComplete"
                    @star="actions.toggleHighlight"
                    @set-status="actions.setStatus"
                    @delete="askDelete"
                />
            </div>
            <EmptyState
                v-else
                icon="tasks"
                :title="hasFilters ? 'No tasks match these filters' : 'No tasks yet'"
                :message="
                    hasFilters
                        ? 'Try widening the filters or clearing the search.'
                        : 'Create your first task to start tracking your work.'
                "
            >
                <Button v-if="hasFilters" variant="outline" @click="clearFilters">Clear filters</Button>
                <Button v-else icon-start="add" @click="create()">Create a task</Button>
            </EmptyState>

            <Pagination v-model:page="filters.page" :last-page="data.meta.last_page" />
        </template>

        <TaskFormDialog v-model:open="dialogOpen" :task="current" :defaults="defaults" @saved="refresh" />
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
