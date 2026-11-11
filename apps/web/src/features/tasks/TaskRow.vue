<script setup lang="ts">
import type { Task } from '@focus/types';
import { taskCoverUrl } from '@/features/covers/covers';
import { dueLabel } from '@/lib/dates';
import { taskRecurrenceLabels } from '@/lib/labels';

/** Compact list row: complete, open, star. Used inside dashboard and detail cards. */
const props = defineProps<{ task: Task }>();
const emit = defineEmits<{ toggle: [task: Task]; star: [task: Task] }>();

const done = computed(() => props.task.status === 'completed');
const local = computed(() => isLocal(props.task));
const cover = computed(() => taskCoverUrl(props.task.cover, true));
</script>

<template>
    <li
        class="group/row @container flex items-center gap-3 rounded-md px-2 py-2 transition-colors duration-(--duration-instant) hover:bg-surface-raised"
    >
        <TaskCheck :task="task" @toggle="emit('toggle', task)" />
        <img
            v-if="cover"
            :src="cover"
            alt=""
            loading="lazy"
            :class="['hidden h-8 w-12 shrink-0 rounded-sm object-cover @sm:block', done && 'opacity-60 grayscale']"
        />
        <div class="min-w-0 flex-1">
            <span
                v-if="local"
                :class="['block truncate text-body font-semibold', done && 'text-fg-muted line-through']"
                >{{ task.title }}</span
            >
            <RouterLink
                v-else
                :to="{ name: 'task-detail', params: { id: task.id } }"
                :class="[
                    'block truncate text-body font-semibold underline-offset-4 hover:underline',
                    done && 'text-fg-muted line-through',
                ]"
            >
                {{ task.title }}
            </RouterLink>
            <p class="flex items-center gap-2 truncate text-caption text-fg-muted">
                <ToneDot v-if="task.goal?.tone" :tone="task.goal.tone" />
                <span v-if="task.goal" class="truncate">{{ task.goal.title }}</span>
                <span v-if="task.goal" aria-hidden="true">·</span>
                <span :class="['tabular', task.is_overdue && 'font-bold text-danger-fg']">{{
                    done ? 'Done' : dueLabel(task.due_date)
                }}</span>
                <span v-if="local" class="inline-flex items-center gap-1"><Icon name="offline" />Not synced yet</span>
                <Icon v-if="task.recurrence && !done" name="retry" :label="taskRecurrenceLabels[task.recurrence]" />
                <span v-if="task.checklist?.total" class="inline-flex items-center gap-1 tabular"
                    ><Icon name="checklist" />{{ task.checklist.done }}/{{ task.checklist.total }}</span
                >
            </p>
        </div>
        <slot name="actions" :task="task" />
        <span class="hidden shrink-0 @md:inline-flex"><PriorityDots :priority="task.priority" /></span>
        <Pressable
            type="button"
            :aria-label="task.is_highlighted ? `Remove “${task.title}” from highlights` : `Highlight “${task.title}”`"
            :aria-pressed="task.is_highlighted"
            :class="[
                'group rounded-full p-1 transition-colors',
                task.is_highlighted ? 'text-highlight-fg' : 'text-fg-subtle hover:text-fg',
            ]"
            @click="emit('star', task)"
        >
            <Icon :name="task.is_highlighted ? 'star-filled' : 'star'" motion="pop" />
        </Pressable>
    </li>
</template>
