<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { SearchKind, SearchResult } from '@focus/types';
import type { IconName } from '@focus/ui';
import type { RouteLocationRaw } from 'vue-router';
import { navigation } from '@/lib/navigation';
import { formatDate } from '@/lib/dates';
import { parseQuickAdd } from '@/lib/quickAdd';

interface Command {
    id: string;
    group: string;
    label: string;
    hint?: string;
    icon: IconName;
    run: () => unknown;
}

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ newTask: []; created: [] }>();

const router = useRouter();
const toast = useToast();
const timer = useTimerStore();
const settings = useSettingsStore();
const preferences = usePreferencesStore();
const goals = useGoalsStore();
const taskTypes = useTaskTypesStore();
const uid = useId();

const query = ref('');
const active = ref(0);
const creating = ref(false);
const list = ref<HTMLElement | null>(null);
const found = ref<SearchResult[]>([]);
const searching = ref(false);
let pending: AbortController | undefined;
let debounce: number | undefined;

const kindIcons: Record<SearchKind, IconName> = {
    task: 'tasks',
    goal: 'target',
    blocker: 'blocker',
    journal: 'journal',
};
const kindLabels: Record<SearchKind, string> = { task: 'Task', goal: 'Goal', blocker: 'Blocker', journal: 'Journal' };

const nextTheme = computed(() => (preferences.resolved === 'dark' ? 'light' : 'dark'));
const quick = computed(() => {
    const text = query.value.trim();
    if (!text) return null;
    if (!settings.values.tasks.quick_add) return { title: text, input: { title: text }, parts: [] };
    return parseQuickAdd(text, { types: taskTypes.types, goals: goals.goals });
});

function go(to: RouteLocationRaw): void {
    void router.push(to);
}

const staticCommands = computed<Command[]>(() => [
    ...navigation.flatMap((section) =>
        section.items.map((item) => ({
            id: `go-${item.name}`,
            group: 'Go to',
            label: item.label,
            icon: item.icon,
            run: () => go({ name: item.name }),
        })),
    ),
    { id: 'go-focus', group: 'Go to', label: 'Focus clock', icon: 'clock', run: () => go({ name: 'focus' }) },
    { id: 'go-settings', group: 'Go to', label: 'Settings', icon: 'user', run: () => go({ name: 'account' }) },
    { id: 'new-task', group: 'Actions', label: 'New task with all fields', icon: 'add', run: () => emit('newTask') },
    {
        id: 'timer',
        group: 'Actions',
        label: timer.status === 'running' ? 'Pause the focus timer' : 'Start the focus timer',
        icon: 'hourglass',
        run: () => timer.toggle(),
    },
    {
        id: 'theme',
        group: 'Actions',
        label: `Switch to ${nextTheme.value} theme`,
        icon: nextTheme.value === 'light' ? 'sun' : 'moon',
        run: () => (preferences.prefs.theme = nextTheme.value),
    },
]);

function target(result: SearchResult): RouteLocationRaw {
    if (result.kind === 'goal') return { name: 'goal-detail', params: { id: result.id } };
    if (result.kind === 'journal') return { name: 'journal', query: { date: result.date } };
    if (result.task_id) return { name: 'task-detail', params: { id: result.task_id } };
    return { name: 'blockers' };
}

const foundCommands = computed<Command[]>(() =>
    found.value.map((result) => ({
        id: `found-${result.kind}-${result.id}`,
        group: 'Found',
        label: result.title,
        hint: [
            kindLabels[result.kind],
            result.kind !== 'journal' && result.date && formatDate(result.date),
            result.excerpt,
        ]
            .filter(Boolean)
            .join(' · '),
        icon: kindIcons[result.kind],
        run: () => go(target(result)),
    })),
);

async function search(text: string): Promise<void> {
    pending?.abort();
    if (text.length < 2) {
        found.value = [];
        return;
    }
    pending = new AbortController();
    searching.value = true;
    try {
        found.value = await api.search.query(text, { signal: pending.signal });
    } catch {
        found.value = [];
    } finally {
        searching.value = false;
    }
}

const commands = computed<Command[]>(() => {
    const text = query.value.trim().toLowerCase();
    const matches = staticCommands.value.filter((command) => !text || command.label.toLowerCase().includes(text));
    if (!quick.value?.title) return [...matches, ...foundCommands.value];
    return [
        {
            id: 'add',
            group: 'Create',
            label: `Add task “${quick.value.title}”`,
            hint: quick.value.parts.join(' · ') || 'Enter to add',
            icon: 'add',
            run: create,
        },
        ...matches,
        ...foundCommands.value,
    ];
});

const groups = computed(() => {
    const result: { name: string; items: { command: Command; index: number }[] }[] = [];
    commands.value.forEach((command, index) => {
        const group = result.find((entry) => entry.name === command.group);
        if (group) group.items.push({ command, index });
        else result.push({ name: command.group, items: [{ command, index }] });
    });
    return result;
});

async function create(): Promise<void> {
    if (!quick.value?.title || creating.value) return;
    creating.value = true;
    try {
        const task = await api.tasks.create(quick.value.input);
        toast.success(`Added “${task.title}”`);
        emit('created');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not add the task.'));
    } finally {
        creating.value = false;
    }
}

async function run(command: Command | undefined): Promise<void> {
    if (!command) return;
    await command.run();
    open.value = false;
}

function onKeydown(event: KeyboardEvent): void {
    const count = commands.value.length;
    if (!count) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = (active.value + (event.key === 'ArrowDown' ? 1 : -1) + count) % count;
        void nextTick(() => list.value?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' }));
    } else if (event.key === 'Enter') {
        event.preventDefault();
        void run(commands.value[active.value]);
    }
}

watch(query, (value) => {
    active.value = 0;
    window.clearTimeout(debounce);
    debounce = window.setTimeout(() => void search(value.trim()), 200);
});
watch(open, (value) => {
    if (!value) return;
    query.value = '';
    found.value = [];
    active.value = 0;
    void goals.load();
    void taskTypes.load();
});
</script>

<template>
    <Dialog
        v-model:open="open"
        title="Quick add and search"
        description="Type a task, like “Send invoice fri !high ~30m”, or search your tasks, goals, blockers, journal and pages."
        :initial-focus="`${uid}-query`"
    >
        <div class="flex flex-col gap-4" @keydown="onKeydown">
            <Input
                :id="`${uid}-query`"
                v-model="query"
                label="Task, search or command"
                role="combobox"
                autocomplete="off"
                :aria-expanded="commands.length > 0"
                :aria-controls="`${uid}-list`"
                :aria-activedescendant="commands[active] ? `${uid}-${commands[active]!.id}` : undefined"
            />
            <div
                :id="`${uid}-list`"
                ref="list"
                role="listbox"
                aria-label="Commands"
                class="flex max-h-96 flex-col gap-3 overflow-y-auto"
            >
                <div
                    v-for="group in groups"
                    :key="group.name"
                    role="group"
                    :aria-label="group.name"
                    class="flex flex-col gap-1"
                >
                    <p class="px-2 text-caption font-semibold text-fg-muted" aria-hidden="true">{{ group.name }}</p>
                    <div
                        v-for="{ command, index } in group.items"
                        :id="`${uid}-${command.id}`"
                        :key="command.id"
                        role="option"
                        :aria-selected="index === active"
                        :class="[
                            'flex cursor-pointer items-center gap-3 rounded-md px-2 py-2',
                            index === active ? 'bg-surface-raised' : 'hover:bg-surface-raised',
                        ]"
                        @mousemove="active = index"
                        @click="run(command)"
                    >
                        <Icon :name="command.icon" />
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate text-label font-semibold">{{ command.label }}</span>
                            <span v-if="command.hint" class="truncate text-caption text-fg-muted">{{
                                command.hint
                            }}</span>
                        </span>
                    </div>
                </div>
                <Text v-if="searching && !found.length" role="label" tone="muted" class="px-2">Searching…</Text>
                <Text v-else-if="!commands.length" role="label" tone="muted" class="px-2">Nothing matches.</Text>
            </div>
            <Text role="caption" tone="muted"
                >Dates: today, tomorrow, fri, next week, in 3 days · !high · ~45m · * star · every week · #type ·
                +goal</Text
            >
        </div>
    </Dialog>
</template>
