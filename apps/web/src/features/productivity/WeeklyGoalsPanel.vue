<script setup lang="ts">
import type { Goal } from '@focus/types';
import { Card, CardHeader, ProgressBar, Text } from '@focus/ui';

defineProps<{ goals: Goal[] }>();
</script>

<template>
    <Card as="section" aria-labelledby="weekly-goals-heading" class="group">
        <CardHeader title="Weekly goals" title-id="weekly-goals-heading" icon="target" />
        <ul v-if="goals.length" class="flex flex-col gap-6">
            <li v-for="goal in goals" :key="goal.id" class="flex flex-col gap-2">
                <div class="flex items-baseline justify-between gap-3">
                    <RouterLink
                        :to="{ name: 'goal-detail', params: { id: goal.id } }"
                        class="truncate font-semibold underline-offset-4 hover:underline"
                    >
                        {{ goal.title }}
                    </RouterLink>
                    <Text as="span" role="label" tone="muted" tabular class="shrink-0">
                        {{ goal.completed_tasks_count }}/{{ goal.tasks_count }} · {{ goal.progress }}%
                    </Text>
                </div>
                <ProgressBar :value="goal.progress" :label="`${goal.title} progress`" />
            </li>
        </ul>
        <Text v-else role="label" tone="muted" class="py-4"
            >No active weekly goals. Create one from the Goals page.</Text
        >
    </Card>
</template>
