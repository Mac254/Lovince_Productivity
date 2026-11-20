<script setup lang="ts">
import type { Task } from '@focus/types';
import { formatDate } from '@/lib/dates';
import type { TaskFolderGroup } from './taskGroups';

/**
 * A goal or type as one slim line: name, open and overdue counts, next due date. It opens in place to
 * the folded task list and remembers, on this device, whether it was left open.
 */
const props = defineProps<{ group: TaskFolderGroup; shownAbove?: ReadonlySet<number> }>();
const emit = defineEmits<{ toggle: [task: Task]; star: [task: Task] }>();

const storageKey = computed(() => `focus.tasks.shelf.${props.group.key}`);

function remembered(): boolean {
    try {
        return localStorage.getItem(storageKey.value) === 'open';
    } catch {
        return false;
    }
}

const open = ref(remembered());
watch(open, (value) => {
    try {
        if (value) localStorage.setItem(storageKey.value, 'open');
        else localStorage.removeItem(storageKey.value);
    } catch {
        /* Storage can be unavailable; the shelf simply starts closed. */
    }
});

const openCount = computed(
    () => props.group.tasks.filter((task) => task.status !== 'completed' && task.status !== 'cancelled').length,
);
const listed = computed(() => props.group.tasks.filter((task) => !props.shownAbove?.has(task.id)));
const above = computed(() => props.group.tasks.length - listed.value.length);
const tone = computed(() => props.group.lead.goal?.tone ?? null);
</script>

<template>
    <li class="flex flex-col">
        <Disclosure v-model:open="open" class="flex flex-col">
            <DisclosureTrigger
                class="flex min-w-0 items-center gap-3 rounded-md px-3 py-3 text-left transition-colors duration-(--duration-instant) hover:bg-surface-raised focus-visible:outline-2 focus-visible:outline-focus-ring"
            >
                <ToneDot v-if="tone" :tone="tone" />
                <span class="min-w-0 flex-1 truncate text-body font-semibold">{{ group.title }}</span>
                <Badge v-if="group.missed" tone="danger" class="shrink-0 tabular">{{ group.missed }} overdue</Badge>
                <span class="hidden shrink-0 text-caption text-fg-muted tabular sm:inline">
                    {{ openCount }} open<template v-if="group.nextDue">
                        · next {{ formatDate(group.nextDue) }}</template
                    >
                </span>
                <Badge class="shrink-0 tabular sm:hidden">{{ openCount }}</Badge>
                <Icon :name="open ? 'chevron-up' : 'chevron-down'" class="shrink-0 text-fg-muted" />
            </DisclosureTrigger>
            <DisclosureContent>
                <p v-if="above" class="px-3 pb-2 text-caption text-fg-muted">
                    {{ above }} {{ above === 1 ? 'task is' : 'tasks are' }} listed under Today above.
                </p>
                <ul v-if="listed.length" class="flex flex-col pb-2 pl-3">
                    <TaskFolderList :tasks="listed" @toggle="emit('toggle', $event)" @star="emit('star', $event)" />
                </ul>
            </DisclosureContent>
        </Disclosure>
    </li>
</template>
