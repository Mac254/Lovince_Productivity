<script setup lang="ts">
import type { Task } from '@focus/types';
import { Icon } from '@focus/ui';
import { computed } from 'vue';
import ToneDot from '@/components/ToneDot.vue';
import { dueLabel } from '@/lib/dates';
import PriorityDots from './PriorityDots.vue';

/** Compact list row: complete, open, star. Used inside dashboard and detail cards. */
const props = defineProps<{ task: Task }>();
const emit = defineEmits<{ toggle: [task: Task]; star: [task: Task] }>();

const done = computed(() => props.task.status === 'completed');
</script>

<template>
    <li
        class="group/row @container flex items-center gap-3 rounded-md px-2 py-2 transition-colors duration-(--duration-instant) hover:bg-surface-raised"
    >
        <button
            type="button"
            :aria-label="done ? `Mark “${task.title}” as not done` : `Mark “${task.title}” complete`"
            :aria-pressed="done"
            :class="[
                'group inline-flex size-(--icon-lg) shrink-0 items-center justify-center rounded-full border-2 transition-colors',
                done
                    ? 'border-highlight bg-highlight text-highlight-ink'
                    : 'border-border-control text-transparent hover:border-highlight-fg hover:text-highlight-fg',
            ]"
            @click="emit('toggle', task)"
        >
            <Icon name="check" motion="pop" />
        </button>
        <div class="min-w-0 flex-1">
            <RouterLink
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
            </p>
        </div>
        <span class="hidden shrink-0 @md:inline-flex"><PriorityDots :priority="task.priority" /></span>
        <button
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
        </button>
    </li>
</template>
