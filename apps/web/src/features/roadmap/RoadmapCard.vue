<script setup lang="ts">
import type { MenuItem } from '@focus/ui';
import { ROADMAP_STAGES, type RoadmapItem, type RoadmapStage } from '@focus/types';
import { formatDate } from '@/lib/dates';
import { roadmapStageLabels } from '@/lib/labels';
import { formatMoney } from '@/features/accounting/money';

const props = defineProps<{ item: RoadmapItem; first: boolean; last: boolean; highlighted?: boolean }>();
const emit = defineEmits<{
    edit: [item: RoadmapItem];
    delete: [item: RoadmapItem];
    move: [item: RoadmapItem, offset: -1 | 1];
    stage: [item: RoadmapItem, stage: RoadmapStage];
}>();

const month = { month: 'short', year: 'numeric' } as const;
const span = computed(() => {
    const { starts_on: start, ends_on: end } = props.item;
    if (start && end) return `${formatDate(start, month)} – ${formatDate(end, month)}`;
    if (start) return `From ${formatDate(start, month)}`;
    if (end) return `Until ${formatDate(end, month)}`;
    return null;
});
const milestones = computed(() => props.item.milestones ?? []);
const done = computed(() => milestones.value.filter((m) => m.status === 'completed').length);
const progress = computed(() =>
    milestones.value.length ? Math.round((done.value / milestones.value.length) * 100) : null,
);

const money = computed(() => {
    const goal = props.item.money_goal;
    if (!goal) return null;
    const target = props.item.target_amount ?? goal.target_amount;
    const saved = Math.min(goal.saved, target);
    return {
        saved: formatMoney(saved, goal.currency),
        target: formatMoney(target, goal.currency),
        left: formatMoney(target - saved, goal.currency),
        percent: target > 0 ? Math.round((saved / target) * 100) : 0,
        name: goal.name,
    };
});

const menu = computed<MenuItem[]>(() => {
    const { item } = props;
    const ordered = item.stage !== 'done';
    return [
        { label: 'Edit', icon: 'edit', onSelect: () => emit('edit', item) },
        ...(ordered && !props.first
            ? [{ label: 'Move earlier', icon: 'chevron-up' as const, onSelect: () => emit('move', item, -1) }]
            : []),
        ...(ordered && !props.last
            ? [{ label: 'Move later', icon: 'chevron-down' as const, onSelect: () => emit('move', item, 1) }]
            : []),
        ...ROADMAP_STAGES.filter((stage) => stage !== item.stage).map((stage) => ({
            label:
                stage === 'done'
                    ? 'Mark done'
                    : stage === 'now'
                      ? 'I’m here now'
                      : `Move to ${roadmapStageLabels[stage]}`,
            icon: (stage === 'done' ? 'check-circle' : 'arrow-right') as 'check-circle' | 'arrow-right',
            onSelect: () => emit('stage', item, stage),
        })),
        { label: 'Delete', icon: 'trash', destructive: true, onSelect: () => emit('delete', item) },
    ];
});
</script>

<template>
    <Card
        :id="`roadmap-item-${item.id}`"
        as="article"
        :class="['gap-2 scroll-mt-24', highlighted && 'outline-2 outline-offset-2 outline-focus-ring']"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="flex min-w-0 flex-col gap-1">
                <Heading :level="3" role="title" class="text-pretty">{{ item.title }}</Heading>
                <div class="flex flex-wrap items-center gap-2">
                    <Text v-if="span" as="span" role="label" tone="muted" tabular>{{ span }}</Text>
                    <Text v-if="item.done_on" as="span" role="label" tone="muted" tabular
                        >Done {{ formatDate(item.done_on) }}</Text
                    >
                </div>
            </div>
            <DropdownMenu :items="menu" :label="`Actions for “${item.title}”`" size="sm" />
        </div>
        <div v-if="money" class="flex flex-col gap-2">
            <div class="flex items-baseline justify-between gap-2">
                <Text as="span" role="label" class="font-semibold" tabular>{{ money.saved }}</Text>
                <Text as="span" role="label" tone="muted" tabular>of {{ money.target }}</Text>
            </div>
            <ProgressBar
                :value="money.percent"
                :label="`${item.title}: ${money.name}`"
                :tone="money.percent === 100 ? 'highlight' : 'accent'"
            />
            <Text role="caption" tone="muted" tabular>
                {{ money.name }} ·
                {{ money.percent === 100 ? 'reached' : `${money.left} to go (${money.percent}%)` }}
            </Text>
        </div>
        <Text v-if="item.goal" role="label" tone="muted" class="flex items-center gap-2">
            <Icon name="target" />
            <RouterLink
                :to="{ name: 'goal-detail', params: { id: item.goal.id } }"
                class="truncate underline-offset-4 hover:underline"
                >{{ item.goal.title }}</RouterLink
            >
        </Text>
        <Text v-if="item.details" role="label" tone="muted" class="line-clamp-4 whitespace-pre-line">{{
            item.details
        }}</Text>
        <div v-if="milestones.length" class="flex flex-col gap-2 border-t border-border pt-3">
            <div class="flex items-baseline justify-between gap-2">
                <Text as="span" role="label" class="font-semibold">Milestones</Text>
                <Text as="span" role="label" tone="muted" tabular>{{ done }} of {{ milestones.length }}</Text>
            </div>
            <ProgressBar
                :value="progress ?? 0"
                :label="`${item.title} milestones`"
                :tone="progress === 100 ? 'highlight' : 'accent'"
            />
            <ul class="flex flex-col gap-1">
                <li v-for="milestone in milestones" :key="milestone.id" class="flex items-start gap-2">
                    <Icon
                        :name="milestone.status === 'completed' ? 'check-circle' : 'circle-dashed'"
                        :class="milestone.status === 'completed' ? 'text-highlight-fg' : 'text-fg-muted'"
                        class="mt-1 shrink-0"
                    />
                    <span class="flex min-w-0 flex-col">
                        <RouterLink
                            :to="{ name: 'task-detail', params: { id: milestone.id } }"
                            :class="[
                                'text-label underline-offset-4 hover:underline',
                                milestone.status === 'completed' && 'text-fg-muted line-through',
                            ]"
                            >{{ milestone.title }}</RouterLink
                        >
                        <Text v-if="milestone.due_date" as="span" role="caption" tone="muted" tabular>{{
                            formatDate(milestone.due_date, { day: 'numeric', month: 'short', year: 'numeric' })
                        }}</Text>
                    </span>
                </li>
            </ul>
        </div>
    </Card>
</template>
