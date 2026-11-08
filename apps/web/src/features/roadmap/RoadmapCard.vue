<script setup lang="ts">
import type { MenuItem } from '@focus/ui';
import { ROADMAP_STAGES, type RoadmapItem, type RoadmapStage } from '@focus/types';
import { formatDate } from '@/lib/dates';
import { roadmapStageLabels } from '@/lib/labels';

const props = defineProps<{ item: RoadmapItem; first: boolean; last: boolean; highlighted?: boolean }>();
const emit = defineEmits<{
    edit: [item: RoadmapItem];
    delete: [item: RoadmapItem];
    move: [item: RoadmapItem, offset: -1 | 1];
    stage: [item: RoadmapItem, stage: RoadmapStage];
}>();

const menu = computed<MenuItem[]>(() => {
    const { item } = props;
    const ordered = item.stage !== 'shipped';
    return [
        { label: 'Edit', icon: 'edit', onSelect: () => emit('edit', item) },
        ...(ordered && !props.first
            ? [{ label: 'Move up', icon: 'chevron-up' as const, onSelect: () => emit('move', item, -1) }]
            : []),
        ...(ordered && !props.last
            ? [{ label: 'Move down', icon: 'chevron-down' as const, onSelect: () => emit('move', item, 1) }]
            : []),
        ...ROADMAP_STAGES.filter((stage) => stage !== item.stage).map((stage) => ({
            label: stage === 'shipped' ? 'Mark shipped' : `Move to ${roadmapStageLabels[stage]}`,
            icon: (stage === 'shipped' ? 'check-circle' : 'arrow-right') as 'check-circle' | 'arrow-right',
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
                    <Badge v-if="item.area" tone="neutral">{{ item.area }}</Badge>
                    <Text v-if="item.shipped_on" as="span" role="label" tone="muted" tabular
                        >Shipped {{ formatDate(item.shipped_on) }}</Text
                    >
                </div>
            </div>
            <DropdownMenu :items="menu" :label="`Actions for “${item.title}”`" size="sm" />
        </div>
        <Text v-if="item.details" role="label" tone="muted" class="line-clamp-4 whitespace-pre-line">{{
            item.details
        }}</Text>
    </Card>
</template>
