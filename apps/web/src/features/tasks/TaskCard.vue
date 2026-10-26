<script setup lang="ts">
import type { Task, TaskStatus } from '@focus/types';
import { Badge, Card, DropdownMenu, Icon, IconButton, Text, type MenuItem } from '@focus/ui';
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import ToneDot from '@/components/ToneDot.vue';
import { dueLabel } from '@/lib/dates';
import { taskCategoryLabels } from '@/lib/labels';
import { toneBorder } from '@/lib/tones';
import PriorityDots from './PriorityDots.vue';
import StatusBadge from './StatusBadge.vue';

const props = defineProps<{ task: Task; featured?: boolean }>();
const emit = defineEmits<{
    edit: [task: Task];
    toggle: [task: Task];
    star: [task: Task];
    delete: [task: Task];
    'set-status': [task: Task, status: TaskStatus];
}>();

const router = useRouter();
const done = computed(() => props.task.status === 'completed');
const closed = computed(() => done.value || props.task.status === 'cancelled');

const menu = computed<MenuItem[]>(() => [
    {
        label: 'View details',
        icon: 'eye',
        onSelect: () => router.push({ name: 'task-detail', params: { id: props.task.id } }),
    },
    { label: 'Edit task', icon: 'edit', onSelect: () => emit('edit', props.task) },
    props.task.status === 'cancelled'
        ? { label: 'Restore task', icon: 'undo', onSelect: () => emit('set-status', props.task, 'not_started') }
        : { label: 'Cancel task', icon: 'close-circle', onSelect: () => emit('set-status', props.task, 'cancelled') },
    { label: 'Delete task', icon: 'trash', destructive: true, onSelect: () => emit('delete', props.task) },
]);
</script>

<template>
    <Card
        :tone="featured ? 'highlight' : 'default'"
        interactive
        :class="[
            'min-h-52 gap-3 border-t-4',
            task.goal?.tone && !featured ? toneBorder[task.goal.tone] : 'border-transparent',
        ]"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <Badge :tone="task.category === 'integration' ? 'accent' : 'neutral'">{{
                    taskCategoryLabels[task.category]
                }}</Badge>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <IconButton
                    :icon="task.is_highlighted ? 'star-filled' : 'star'"
                    :label="
                        task.is_highlighted ? `Remove “${task.title}” from highlights` : `Highlight “${task.title}”`
                    "
                    :pressed="task.is_highlighted"
                    variant="ghost"
                    size="sm"
                    @click="emit('star', task)"
                />
                <IconButton
                    :icon="done ? 'undo' : 'check'"
                    :label="done ? `Mark “${task.title}” as not done` : `Mark “${task.title}” complete`"
                    :variant="done ? 'primary' : 'outline'"
                    :pressed="done"
                    size="sm"
                    @click="emit('toggle', task)"
                />
                <DropdownMenu :items="menu" :label="`Actions for “${task.title}”`" size="sm" />
            </div>
        </div>

        <h3 class="font-display text-title font-medium tracking-tight">
            <RouterLink
                :to="{ name: 'task-detail', params: { id: task.id } }"
                :class="['underline-offset-4 hover:underline', closed && 'line-through decoration-2']"
            >
                {{ task.title }}
            </RouterLink>
        </h3>
        <Text v-if="task.description" role="label" tone="muted" class="line-clamp-2">{{ task.description }}</Text>

        <dl class="flex flex-wrap gap-x-4 gap-y-2 text-label">
            <div class="flex items-center gap-2">
                <dt class="sr-only">Due</dt>
                <Icon name="calendar" />
                <Text
                    as="dd"
                    role="label"
                    :tone="task.is_overdue ? 'danger' : 'muted'"
                    :weight="task.is_overdue ? 'bold' : 'medium'"
                    tabular
                >
                    {{ done ? 'Done' : dueLabel(task.due_date) }}
                </Text>
            </div>
            <div v-if="task.goal" class="flex min-w-0 items-center gap-2">
                <dt class="sr-only">Goal</dt>
                <ToneDot v-if="task.goal.tone" :tone="task.goal.tone" />
                <Text as="dd" role="label" tone="muted" truncate>{{ task.goal.title }}</Text>
            </div>
        </dl>

        <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-2">
            <StatusBadge :status="task.status" />
            <PriorityDots :priority="task.priority" />
        </div>
    </Card>
</template>
