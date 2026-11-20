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
import type { LocationQuery } from 'vue-router';
import { groupByDue } from '@/features/tasks/dueGroups';
import { groupByTitle, groupInto, normaliseTitle, type TaskFolderBy } from '@/features/tasks/folders/taskGroups';
import { buildRoutines, routineKeys } from '@/features/tasks/routines';
import { useTaskEditing } from '@/features/tasks/useTaskEditing';
import { todayIso } from '@/lib/dates';
import { allFacet, withoutAll } from '@/lib/facets';
import { taskPriorityOptions } from '@/lib/labels';
import { fromKind, kindOptions, type TaskKind } from '@/lib/taskKind';

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
    /** Goal folders unless the URL says otherwise; `group=none` turns folders off. */
    group: (route.query.group === 'none'
        ? ''
        : fromQuery<TaskFolderBy>(route.query, 'group', ['goal', 'type']) || 'goal') as TaskFolderBy | '',
    page: Number(route.query.page) || 1,
});

const statusFacet = allFacet(toRef(filters, 'status'));
const statusOptions = withoutAll(statusChips);

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
const dueOptions = TASK_DUE_FILTERS.map((value) => ({ value, label: dueLabels[value] }));
/** No sort chosen is the API's smart order (open first). */
const sortOptions = TASK_SORTS.map((value) => ({ value, label: sortLabels[value] }));
const groupOptions: { value: TaskFolderBy; label: string }[] = [
    { value: 'goal', label: 'Goal' },
    { value: 'type', label: 'Type' },
];
const view = ref<'cards' | 'list'>(route.query.view === 'list' ? 'list' : 'cards');
const viewOptions = [
    { value: 'cards' as const, label: 'Cards' },
    { value: 'list' as const, label: 'List' },
];
const typeOptions = computed(() => kindOptions(taskTypes.types));
const typeFacet = computed<TaskKind | ''>({
    get: () => {
        if (filters.task_type_id) return `type:${filters.task_type_id}` as TaskKind;
        return filters.category ? (`category:${filters.category}` as TaskKind) : '';
    },
    set: (kind: TaskKind | '') => {
        const next = kind ? fromKind(kind) : null;
        filters.task_type_id = next?.task_type_id ?? '';
        filters.category = next && !next.task_type_id ? next.category : '';
    },
});
const goalOptions = computed(() => goals.goals.map((goal) => ({ value: goal.id, label: goal.title })));

/** Folders are built from one page, so a grouped page takes the most the API allows to keep folders whole. */
const perPage = computed(() => (filters.group ? LIMITS.perPage : 24));

const query = computed<TaskFilters>(() => ({
    search: filters.search || undefined,
    status: filters.status === 'all' ? undefined : filters.status,
    priority: filters.priority || undefined,
    category: filters.category || undefined,
    due: filters.due || undefined,
    hide_planned_ahead: filters.due === 'upcoming' ? undefined : true,
    goal_id: filters.goal_id || undefined,
    task_type_id: filters.task_type_id || undefined,
    sort: filters.sort || undefined,
    direction: filters.sort === 'created_at' || filters.sort === 'updated_at' ? 'desc' : undefined,
    page: filters.page,
    per_page: perPage.value,
}));

const outbox = useOutboxStore();
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

        syncQuery();

        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(run, next.search !== prev.search ? 300 : 0);
    },
);
watch(view, syncQuery);
watch(
    () => outbox.synced,
    () => {
        void run();
        void closedRecently.run();
    },
);

function syncQuery(): void {
    const urlQuery = Object.fromEntries(
        Object.entries(filters)
            .map(([key, value]) => [key, key === 'group' && value === '' ? 'none' : value] as const)
            .filter(
                ([key, value]) =>
                    value !== '' &&
                    !(key === 'page' && value === 1) &&
                    !(key === 'status' && value === 'open') &&
                    !(key === 'group' && value === 'goal'),
            )
            .map(([key, value]) => [key, String(value)]),
    );
    void router.replace({ query: view.value === 'list' ? { ...urlQuery, view: 'list' } : urlQuery });
}

const onlyStatus = computed(
    () =>
        !filters.search &&
        !filters.priority &&
        !filters.category &&
        !filters.due &&
        !filters.goal_id &&
        !filters.task_type_id &&
        filters.page === 1,
);
const tasks = computed(() =>
    outbox.withPending(
        data.value?.data ?? [],
        (task) =>
            onlyStatus.value &&
            (filters.status === 'all' ||
                (filters.status === 'open'
                    ? !['completed', 'cancelled'].includes(task.status)
                    : task.status === filters.status)),
    ),
);
const sections = computed(() => {
    if (filters.group) {
        return [
            {
                key: 'all',
                title: '',
                icon: 'tasks' as const,
                tasks: tasks.value,
                groups: groupInto(tasks.value, filters.group),
                count: tasks.value.length,
            },
        ];
    }
    const titled = groupByTitle(tasks.value);
    const byLead = new Map(titled.map((group) => [group.lead.id, group]));
    const sorted = filters.sort
        ? [{ key: 'all', title: '', icon: 'tasks' as const, tasks: titled.map((group) => group.lead) }]
        : groupByDue(titled.map((group) => group.lead));
    return sorted.map((section) => ({
        ...section,
        groups: section.tasks.map((lead) => byLead.get(lead.id)!),
        count: section.tasks.reduce((sum, lead) => sum + byLead.get(lead.id)!.tasks.length, 0),
    }));
});

/** With folders on and only the default Open filter, the page leads with today: routines, then what's due. */
const focusMode = computed(() => !!filters.group && filters.status === 'open' && onlyStatus.value);
const closedRecently = useAsync((signal) =>
    api.tasks.list(
        { status: 'completed', sort: 'updated_at', direction: 'desc', per_page: LIMITS.perPage },
        { signal },
    ),
);
const isOpen = (task: { status: TaskStatus }) => task.status !== 'completed' && task.status !== 'cancelled';
const routines = computed(() =>
    focusMode.value ? buildRoutines(tasks.value.filter(isOpen), closedRecently.data.value?.data ?? []) : [],
);
const dueNow = computed(() => {
    const today = todayIso();
    const repeated = routineKeys(tasks.value.filter(isOpen));
    return tasks.value
        .filter((task) => isOpen(task) && task.due_date && task.due_date <= today)
        .filter((task) => !repeated.has(normaliseTitle(task.title)))
        .sort((a, b) => a.due_date!.localeCompare(b.due_date!));
});
const tally = computed(() => {
    const today = todayIso();
    const open = tasks.value.filter((task) => isOpen(task) && task.due_date);
    return {
        today: open.filter((task) => task.due_date === today).length,
        overdue: open.filter((task) => task.due_date! < today).length,
    };
});
const shownAbove = computed(
    () => new Set([...dueNow.value, ...routines.value.map((routine) => routine.task)].map((task) => task.id)),
);
const folderNoun = computed(() => (filters.group === 'type' ? 'type' : 'goal'));

const total = computed(
    () =>
        (data.value?.meta.total ?? 0) +
        sections.value.reduce((sum, section) => sum + section.count, 0) -
        (data.value?.data.length ?? 0),
);

const summary = computed(() => {
    if (!data.value) return loading.value ? 'Loading…' : 'Everything you’ve planned, in one place.';
    const count = `${total.value} ${total.value === 1 ? 'task' : 'tasks'}`;
    if (!focusMode.value) return `${count} match these filters.`;
    const parts = [`${tally.value.today} due today`];
    if (tally.value.overdue) parts.push(`${tally.value.overdue} overdue`);
    parts.push(`${total.value} open`);
    return parts.join(' · ');
});

const moreFilters = computed(
    () =>
        [filters.priority, filters.category || filters.task_type_id, filters.due, filters.goal_id].filter(Boolean)
            .length,
);
const showMore = ref(moreFilters.value > 0);

const refresh = async () => {
    void closedRecently.run();
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
    void closedRecently.run();
    goals.load().catch(() => undefined);
    taskTypes.load().catch(() => undefined);
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader title="Tasks" title-id="page-title" :description="summary">
            <template #actions>
                <Button variant="outline" icon-start="tag" :to="{ name: 'types' }">Types</Button>
                <Button icon-start="add" @click="create(filters.goal_id ? { goal_id: filters.goal_id } : {})"
                    >New task</Button
                >
            </template>
        </PageHeader>

        <section aria-label="Filters" class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <SearchField
                    id="task-search"
                    v-model="filters.search"
                    label="Search tasks"
                    placeholder="Search tasks"
                    :maxlength="LIMITS.search"
                    class="w-full sm:w-80"
                />
                <FacetFilter v-model="statusFacet" label="Status" :options="statusOptions" />
                <Button
                    variant="ghost"
                    size="sm"
                    icon-start="filter"
                    :aria-expanded="showMore"
                    aria-controls="task-more-filters"
                    @click="showMore = !showMore"
                    >{{ moreFilters ? `Filters · ${moreFilters}` : 'More filters' }}</Button
                >
                <Button v-if="hasFilters" variant="ghost" size="sm" icon-start="close" @click="clearFilters"
                    >Clear</Button
                >
            </div>
            <div v-show="showMore" id="task-more-filters" class="flex flex-wrap items-center gap-2">
                <FacetFilter v-model="filters.priority" label="Priority" :options="taskPriorityOptions" />
                <FacetFilter v-model="typeFacet" label="Type" :options="typeOptions" />
                <FacetFilter v-model="filters.due" label="Due" :options="dueOptions" />
                <FacetFilter v-model="filters.goal_id" label="Goal" :options="goalOptions" />
                <span class="mx-1 hidden h-6 w-px bg-border sm:block" aria-hidden="true" />
                <FacetFilter v-model="filters.group" label="Group" :options="groupOptions" />
                <FacetFilter v-model="filters.sort" label="Sort" :options="sortOptions" />
                <ChipGroup v-if="!filters.group" v-model="view" label="View" :options="viewOptions" />
            </div>
        </section>

        <ErrorState v-if="error" :message="error" @retry="run" />

        <div v-else-if="loading && !data && filters.group" class="flex flex-col gap-2" aria-hidden="true">
            <Skeleton v-for="n in 6" :key="n" class="h-12" />
        </div>
        <div v-else-if="loading && !data" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-hidden="true">
            <Skeleton v-for="n in 6" :key="n" class="h-52" />
        </div>

        <template v-else-if="data">
            <div
                v-if="sections.some((section) => section.count)"
                :class="['flex flex-col gap-8 transition-opacity duration-(--duration-fast)', loading && 'opacity-60']"
            >
                <template v-if="focusMode">
                    <RoutineStrip v-if="routines.length" :routines="routines" @toggle="actions.toggleComplete" />
                    <section v-if="dueNow.length" aria-labelledby="due-now-title" class="flex flex-col gap-3">
                        <header class="flex items-center gap-2">
                            <Icon name="calendar" class="text-fg-muted" />
                            <Text id="due-now-title" as="h2" role="label" weight="bold">Today</Text>
                            <Text as="span" role="caption" tone="muted" tabular>{{ dueNow.length }}</Text>
                        </header>
                        <Card as="div" padding="none" class="p-1">
                            <ul class="flex flex-col">
                                <TaskRow
                                    v-for="task in dueNow"
                                    :key="task.id"
                                    :task="task"
                                    @toggle="actions.toggleComplete"
                                    @star="actions.toggleHighlight"
                                />
                            </ul>
                        </Card>
                    </section>
                </template>
                <section
                    v-for="section in sections"
                    :key="section.key"
                    :aria-labelledby="filters.group ? 'group-all' : section.title ? `group-${section.key}` : undefined"
                    :aria-label="filters.group || section.title ? undefined : 'Tasks'"
                    class="flex flex-col gap-3"
                >
                    <header v-if="filters.group" class="flex items-center gap-2">
                        <Icon name="folder" class="text-fg-muted" />
                        <Text id="group-all" as="h2" role="label" weight="bold">By {{ folderNoun }}</Text>
                        <Text as="span" role="caption" tone="muted" tabular>{{ section.groups.length }}</Text>
                    </header>
                    <header v-else-if="section.title" class="flex items-center gap-2">
                        <Icon
                            :name="section.icon"
                            :class="section.key === 'overdue' ? 'text-danger-fg' : 'text-fg-muted'"
                        />
                        <Text :id="`group-${section.key}`" as="h2" role="label" weight="bold">{{ section.title }}</Text>
                        <Text as="span" role="caption" tone="muted" tabular>{{ section.count }}</Text>
                    </header>
                    <Card v-if="filters.group" as="div" padding="none" class="p-1">
                        <ul class="flex flex-col divide-y divide-border">
                            <TaskShelf
                                v-for="group in section.groups"
                                :key="group.key"
                                :group="group"
                                :shown-above="focusMode ? shownAbove : undefined"
                                @toggle="actions.toggleComplete"
                                @star="actions.toggleHighlight"
                            />
                        </ul>
                    </Card>
                    <ul
                        v-else-if="view === 'cards'"
                        class="grid grid-flow-row-dense gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <template v-for="group in section.groups" :key="group.key">
                            <li v-if="group.tasks.length === 1" class="flex flex-col">
                                <TaskCard
                                    :task="group.lead"
                                    class="flex-1"
                                    @edit="edit"
                                    @toggle="actions.toggleComplete"
                                    @star="actions.toggleHighlight"
                                    @set-status="actions.setStatus"
                                    @delete="askDelete"
                                />
                            </li>
                            <TaskFolder
                                v-else
                                :group="group"
                                variant="card"
                                @toggle="actions.toggleComplete"
                                @star="actions.toggleHighlight"
                            />
                        </template>
                    </ul>
                    <Card v-else as="div" padding="none" class="p-2">
                        <ul class="flex flex-col">
                            <template v-for="group in section.groups" :key="group.key">
                                <TaskRow
                                    v-if="group.tasks.length === 1"
                                    :task="group.lead"
                                    @toggle="actions.toggleComplete"
                                    @star="actions.toggleHighlight"
                                />
                                <TaskFolder
                                    v-else
                                    :group="group"
                                    @toggle="actions.toggleComplete"
                                    @star="actions.toggleHighlight"
                                />
                            </template>
                        </ul>
                    </Card>
                </section>
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

            <Pagination
                v-model:page="filters.page"
                :last-page="data.meta.last_page"
                :total="data.meta.total"
                :per-page="data.meta.per_page"
                noun="tasks"
            />
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
