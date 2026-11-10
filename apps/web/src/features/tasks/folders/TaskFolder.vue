<script setup lang="ts">
import type { Task } from '@focus/types';
import { taskRecurrenceLabels } from '@/lib/labels';
import type { TaskTitleGroup } from './titleGroups';

/** Same-titled tasks as one folder: progress across them on the outside, each instance inside. */
const props = defineProps<{ group: TaskTitleGroup }>();
const emit = defineEmits<{ toggle: [task: Task]; star: [task: Task] }>();
defineSlots<{ actions?: (props: { task: Task }) => unknown }>();

const open = ref(false);
const recurrence = computed(() => props.group.tasks.find((task) => task.recurrence)?.recurrence ?? null);
</script>

<template>
    <li>
        <Disclosure v-model:open="open" class="flex flex-col">
            <div
                class="flex flex-col gap-2 rounded-md px-2 py-2 transition-colors duration-(--duration-instant) hover:bg-surface-raised"
            >
                <DisclosureTrigger class="flex min-w-0 items-center gap-3 rounded-sm text-left">
                    <Icon
                        :name="open ? 'folder-open' : 'folder'"
                        size="lg"
                        :class="group.missed ? 'text-danger-fg' : 'text-highlight-fg'"
                    />
                    <span class="min-w-0 flex-1 truncate text-body font-semibold">{{ group.lead.title }}</span>
                    <Icon
                        v-if="recurrence"
                        name="retry"
                        class="shrink-0 text-fg-muted"
                        :label="taskRecurrenceLabels[recurrence]"
                    />
                    <Badge class="shrink-0 tabular">{{ group.tasks.length }}</Badge>
                    <Icon :name="open ? 'chevron-down' : 'chevron-right'" class="shrink-0 text-fg-muted" />
                    <VisuallyHidden>{{ group.tasks.length }} tasks</VisuallyHidden>
                </DisclosureTrigger>
                <TaskFolderSummary :group="group" class="pl-8" />
            </div>
            <DisclosureContent>
                <ul class="ml-4 flex flex-col border-l border-border pl-2">
                    <TaskRow
                        v-for="task in group.tasks"
                        :key="task.id"
                        :task="task"
                        @toggle="emit('toggle', $event)"
                        @star="emit('star', $event)"
                    >
                        <template v-if="$slots.actions" #actions="slotProps">
                            <slot name="actions" v-bind="slotProps" />
                        </template>
                    </TaskRow>
                </ul>
            </DisclosureContent>
        </Disclosure>
    </li>
</template>
