<script setup lang="ts">
import type { Task } from '@focus/types';
import { dueLabel } from '@/lib/dates';

defineProps<{ tasks: Task[]; empty: string; action?: string; busy?: number | null; today?: string }>();
defineEmits<{ act: [task: Task] }>();
</script>

<template>
    <ul v-if="tasks.length" class="flex flex-col divide-y divide-border">
        <li v-for="task in tasks" :key="task.id" class="flex items-center justify-between gap-3 py-2">
            <span class="flex min-w-0 flex-col">
                <RouterLink
                    :to="{ name: 'task-detail', params: { id: task.id } }"
                    class="truncate font-semibold underline-offset-4 hover:underline"
                    >{{ task.title }}</RouterLink
                >
                <Text v-if="task.due_date" role="caption" tone="muted" tabular>{{
                    dueLabel(task.due_date, today)
                }}</Text>
            </span>
            <span class="flex shrink-0 items-center gap-2">
                <TaskTypeBadge :task="task" />
                <Button
                    v-if="action"
                    variant="ghost"
                    size="sm"
                    icon-end="arrow-right"
                    :loading="busy === task.id"
                    @click="$emit('act', task)"
                    >{{ action }}</Button
                >
            </span>
        </li>
    </ul>
    <Text v-else role="label" tone="muted">{{ empty }}</Text>
</template>
