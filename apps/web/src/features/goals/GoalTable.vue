<script setup lang="ts">
import type { Goal } from '@focus/types';
import type { MenuItem } from '@focus/ui';
import { formatDate } from '@/lib/dates';
import { goalStatusLabels, goalStatusTones, goalTimeframeLabels } from '@/lib/labels';
import { sortGoals, type GoalSort, type GoalSortKey } from './sortGoals';

const props = defineProps<{ goals: Goal[] }>();
const emit = defineEmits<{ edit: [goal: Goal]; delete: [goal: Goal] }>();

const sort = ref<GoalSort>({ key: 'created', direction: 'desc' });
const columns: { key: GoalSortKey; label: string; descFirst?: boolean }[] = [
    { key: 'title', label: 'Goal' },
    { key: 'status', label: 'Status' },
    { key: 'timeframe', label: 'Timeframe' },
    { key: 'progress', label: 'Progress', descFirst: true },
    { key: 'target', label: 'Target' },
    { key: 'created', label: 'Added', descFirst: true },
];

const sorted = computed(() => sortGoals(props.goals, sort.value));
const paged = usePagedList(sorted, 15);

watch(
    () => [sort.value.key, sort.value.direction, props.goals.length],
    () => (paged.page = 1),
);

function menu(goal: Goal): MenuItem[] {
    return [
        { label: 'Edit goal', icon: 'edit', onSelect: () => emit('edit', goal) },
        { label: 'Delete goal', icon: 'trash', destructive: true, onSelect: () => emit('delete', goal) },
    ];
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <Card>
            <Table caption="Goals" label="Goals">
                <TableHeader>
                    <TableRow>
                        <SortableHead
                            v-for="column in columns"
                            :key="column.key"
                            v-model:sort="sort"
                            :column="column.key"
                            :label="column.label"
                            :desc-first="column.descFirst"
                            :class="column.key === 'title' && 'w-full'"
                        />
                        <TableHead align="end"><VisuallyHidden>Actions</VisuallyHidden></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="goal in paged.visible" :key="goal.id">
                        <TableCell>
                            <div class="flex min-w-48 items-center gap-2">
                                <ToneDot :tone="goal.tone" />
                                <RouterLink
                                    :to="{ name: 'goal-detail', params: { id: goal.id } }"
                                    class="font-medium text-fg underline-offset-4 hover:underline"
                                    >{{ goal.title }}</RouterLink
                                >
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge dot :tone="goalStatusTones[goal.status]">{{ goalStatusLabels[goal.status] }}</Badge>
                        </TableCell>
                        <TableCell class="whitespace-nowrap text-fg-muted">{{
                            goalTimeframeLabels[goal.timeframe]
                        }}</TableCell>
                        <TableCell>
                            <div class="flex min-w-36 flex-col gap-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="font-medium tabular">{{ goal.progress }}%</span>
                                    <Text as="span" role="caption" tone="muted" tabular
                                        >{{ goal.completed_tasks_count }}/{{ goal.tasks_count }}</Text
                                    >
                                </div>
                                <ProgressBar
                                    :value="goal.progress"
                                    :label="`${goal.title} progress`"
                                    :tone="goal.progress === 100 ? 'highlight' : 'accent'"
                                />
                            </div>
                        </TableCell>
                        <TableCell numeric class="whitespace-nowrap text-fg-muted">{{
                            goal.target_date ? formatDate(goal.target_date) : '—'
                        }}</TableCell>
                        <TableCell numeric class="whitespace-nowrap text-fg-muted">{{
                            formatDate(goal.created_at)
                        }}</TableCell>
                        <TableCell align="end">
                            <DropdownMenu :items="menu(goal)" :label="`Actions for “${goal.title}”`" size="sm" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </Card>
        <Pagination v-model:page="paged.page" :last-page="paged.lastPage" />
    </div>
</template>
