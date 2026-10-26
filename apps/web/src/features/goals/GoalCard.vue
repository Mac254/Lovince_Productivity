<script setup lang="ts">
import type { Goal } from '@focus/types';
import { Badge, Card, DropdownMenu, Icon, ProgressBar, Text, type MenuItem } from '@focus/ui';
import { computed } from 'vue';
import ToneDot from '@/components/ToneDot.vue';
import { formatDate } from '@/lib/dates';
import { goalStatusLabels, goalTimeframeLabels } from '@/lib/labels';
import { toneBorder } from '@/lib/tones';

const props = defineProps<{ goal: Goal }>();
const emit = defineEmits<{ edit: [goal: Goal]; delete: [goal: Goal] }>();

const menu = computed<MenuItem[]>(() => [
    { label: 'Edit goal', icon: 'edit', onSelect: () => emit('edit', props.goal) },
    { label: 'Delete goal', icon: 'trash', destructive: true, onSelect: () => emit('delete', props.goal) },
]);

const dateRange = computed(() => {
    const { start_date: start, target_date: target } = props.goal;
    if (start && target) return `${formatDate(start)} – ${formatDate(target)}`;
    if (target) return `By ${formatDate(target)}`;
    return start ? `From ${formatDate(start)}` : 'No target date';
});

const taskSummary = computed(() => {
    const { tasks_count: total, completed_tasks_count: done } = props.goal;
    return total === 0 ? 'No tasks yet' : `${done} of ${total} ${total === 1 ? 'task' : 'tasks'}`;
});
</script>

<template>
    <Card interactive :class="['min-h-56 gap-3 border-t-4', toneBorder[goal.tone]]">
        <div class="flex items-start justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <Badge tone="inverse"><ToneDot :tone="goal.tone" />{{ goalTimeframeLabels[goal.timeframe] }}</Badge>
                <Badge v-if="goal.status !== 'active'" variant="outline">{{ goalStatusLabels[goal.status] }}</Badge>
            </div>
            <DropdownMenu :items="menu" :label="`Actions for “${goal.title}”`" size="sm" />
        </div>

        <h3 class="font-display text-title font-medium tracking-tight">
            <RouterLink
                :to="{ name: 'goal-detail', params: { id: goal.id } }"
                class="underline-offset-4 hover:underline"
                >{{ goal.title }}</RouterLink
            >
        </h3>
        <Text v-if="goal.description" role="label" tone="muted" class="line-clamp-2">{{ goal.description }}</Text>
        <Text role="label" tone="muted" tabular class="flex items-center gap-2">
            <Icon name="calendar-range" />
            {{ dateRange }}
        </Text>

        <div class="mt-auto flex flex-col gap-2 pt-2">
            <div class="flex items-baseline justify-between gap-2">
                <span class="font-display text-heading font-semibold tabular">{{ goal.progress }}%</span>
                <Text as="span" role="label" tone="muted" tabular>{{ taskSummary }}</Text>
            </div>
            <ProgressBar
                :value="goal.progress"
                :label="`${goal.title} progress`"
                :tone="goal.progress === 100 ? 'highlight' : 'accent'"
            />
        </div>
    </Card>
</template>
