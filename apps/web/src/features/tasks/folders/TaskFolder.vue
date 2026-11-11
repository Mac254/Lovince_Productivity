<script setup lang="ts">
import type { Task } from '@focus/types';
import { taskRecurrenceLabels } from '@/lib/labels';
import type { TaskFolderGroup } from './taskGroups';

/**
 * A folder of tasks — one repeated title, or a whole goal or type — with progress across them on the
 * outside. As a card it sits in a grid and spans the whole row once opened, so its tasks have room.
 */
const props = withDefaults(defineProps<{ group: TaskFolderGroup; variant?: 'row' | 'card' }>(), { variant: 'row' });
const emit = defineEmits<{ toggle: [task: Task]; star: [task: Task] }>();
defineSlots<{ actions?: (props: { task: Task }) => unknown }>();

const open = ref(false);
const recurrence = computed(() =>
    props.group.nested ? null : (props.group.tasks.find((task) => task.recurrence)?.recurrence ?? null),
);
</script>

<template>
    <li v-if="variant === 'card'" :class="open && 'col-span-full'">
        <Card as="div" padding="none" :interactive="!open" class="h-full">
            <Disclosure v-model:open="open" class="flex h-full flex-col">
                <div class="relative flex flex-1 flex-col gap-4 p-4 sm:p-6">
                    <DisclosureTrigger
                        class="flex w-full items-start gap-3 rounded-sm text-left after:absolute after:inset-0 after:rounded-lg"
                    >
                        <span
                            class="flex size-(--control-lg) shrink-0 items-center justify-center rounded-md"
                            :class="
                                group.missed ? 'bg-danger-subtle text-danger-fg' : 'bg-success-subtle text-highlight-fg'
                            "
                        >
                            <Icon :name="open ? 'folder-open' : 'folder'" size="lg" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="line-clamp-2 text-body font-semibold">{{ group.title }}</span>
                            <span class="flex items-center gap-1 text-caption text-fg-muted tabular">
                                <Icon v-if="recurrence" name="retry" :label="taskRecurrenceLabels[recurrence]" />
                                {{ group.tasks.length }} {{ group.tasks.length === 1 ? 'task' : 'tasks' }}
                            </span>
                        </span>
                        <Icon :name="open ? 'chevron-up' : 'chevron-down'" class="mt-1 shrink-0 text-fg-muted" />
                    </DisclosureTrigger>
                    <TaskFolderSummary :group="group" class="mt-auto" />
                </div>
                <DisclosureContent>
                    <ul class="flex flex-col border-t border-border p-2">
                        <TaskFolderList
                            v-if="group.nested"
                            :tasks="group.tasks"
                            @toggle="emit('toggle', $event)"
                            @star="emit('star', $event)"
                        >
                            <template v-if="$slots.actions" #actions="slotProps">
                                <slot name="actions" v-bind="slotProps" />
                            </template>
                        </TaskFolderList>
                        <template v-else>
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
                        </template>
                    </ul>
                </DisclosureContent>
            </Disclosure>
        </Card>
    </li>
    <li v-else>
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
                    <span class="min-w-0 flex-1 truncate text-body font-semibold">{{ group.title }}</span>
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
                    <TaskFolderList
                        v-if="group.nested"
                        :tasks="group.tasks"
                        @toggle="emit('toggle', $event)"
                        @star="emit('star', $event)"
                    >
                        <template v-if="$slots.actions" #actions="slotProps">
                            <slot name="actions" v-bind="slotProps" />
                        </template>
                    </TaskFolderList>
                    <template v-else>
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
                    </template>
                </ul>
            </DisclosureContent>
        </Disclosure>
    </li>
</template>
