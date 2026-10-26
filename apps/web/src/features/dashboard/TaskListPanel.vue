<script setup lang="ts">
import type { Task } from '@focus/types';
import { Card, CardHeader, Text, type IconName } from '@focus/ui';
import ToneDot from '@/components/ToneDot.vue';
import { dueLabel, relativeTime } from '@/lib/dates';
import { taskStatusLabels } from '@/lib/labels';

defineProps<{
    title: string;
    tasks: Task[];
    icon: IconName;
    empty: string;
    mode?: 'due' | 'activity';
}>();

function activity(task: Task): string {
    if (task.completed_at && task.status === 'completed') return `Completed ${relativeTime(task.completed_at)}`;
    if (task.created_at === task.updated_at) return `Added ${relativeTime(task.created_at)}`;
    return `${taskStatusLabels[task.status]} · updated ${relativeTime(task.updated_at)}`;
}
</script>

<template>
    <Card as="section" data-stagger class="group">
        <CardHeader :title="title" :icon="icon">
            <slot name="actions" />
        </CardHeader>
        <slot name="lead" />
        <ul v-if="tasks.length" class="-mx-2 flex flex-col">
            <li v-for="task in tasks" :key="task.id">
                <RouterLink
                    :to="{ name: 'task-detail', params: { id: task.id } }"
                    class="flex items-center justify-between gap-3 rounded-md px-2 py-2 transition-colors duration-(--duration-instant) hover:bg-surface-raised"
                >
                    <span class="min-w-0">
                        <Text as="span" weight="semibold" truncate class="block">{{ task.title }}</Text>
                        <span class="flex items-center gap-2 text-label text-fg-muted">
                            <ToneDot v-if="task.goal?.tone && mode !== 'activity'" :tone="task.goal.tone" />
                            <span class="truncate">{{
                                mode === 'activity'
                                    ? activity(task)
                                    : (task.goal?.title ?? taskStatusLabels[task.status])
                            }}</span>
                        </span>
                    </span>
                    <Text
                        v-if="mode !== 'activity'"
                        as="span"
                        role="caption"
                        weight="semibold"
                        :tone="task.is_overdue ? 'danger' : 'muted'"
                        tabular
                        class="shrink-0 text-right"
                    >
                        {{ dueLabel(task.due_date) }}
                    </Text>
                </RouterLink>
            </li>
        </ul>
        <Text v-else role="label" tone="muted" class="py-4">{{ empty }}</Text>
    </Card>
</template>
