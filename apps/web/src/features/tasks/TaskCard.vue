<script setup lang="ts">
import type { Task, TaskStatus } from '@focus/types';
import type { MenuItem } from '@focus/ui';
import { taskCoverUrl } from '@/features/covers/covers';
import { dueLabel } from '@/lib/dates';
import { taskRecurrenceLabels } from '@/lib/labels';
import { toneBorder } from '@/lib/tones';

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
const settings = useSettingsStore();
const compact = computed(() => settings.values.tasks.card_density === 'compact');
const cover = computed(() => (settings.values.tasks.card_covers ? taskCoverUrl(props.task.cover) : null));
const local = computed(() => isLocal(props.task));
const showDescription = computed(() => settings.values.tasks.card_descriptions && !compact.value);

const menu = computed<MenuItem[]>(() => [
    ...(local.value
        ? []
        : [
              {
                  label: 'View details',
                  icon: 'eye' as const,
                  onSelect: () => router.push({ name: 'task-detail', params: { id: props.task.id } }),
              },
          ]),
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
            'group/card border-t-4',
            compact ? 'gap-2' : 'min-h-52 gap-3',
            task.goal?.tone && !featured ? toneBorder[task.goal.tone] : 'border-transparent',
        ]"
    >
        <div v-if="cover" class="relative -mx-4 -mt-4 overflow-hidden rounded-t-lg sm:-mx-6 sm:-mt-6">
            <img
                :src="cover"
                alt=""
                loading="lazy"
                :class="[
                    'w-full object-cover transition-transform duration-(--duration-slow) group-hover/card:scale-105',
                    compact ? 'h-20' : 'h-32',
                    closed && 'opacity-60 grayscale',
                ]"
            />
        </div>
        <div class="flex items-center justify-between gap-2">
            <TaskTypeBadge :task="task" />
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
                <DropdownMenu :items="menu" :label="`Actions for “${task.title}”`" size="sm" />
            </div>
        </div>

        <div class="flex items-start gap-3">
            <TaskCheck :task="task" size="lg" @toggle="emit('toggle', task)" />
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <Heading :level="3" role="title" class="line-clamp-2">
                    <span v-if="local" :class="closed && 'text-fg-muted line-through decoration-2'">{{
                        task.title
                    }}</span>
                    <RouterLink
                        v-else
                        :to="{ name: 'task-detail', params: { id: task.id } }"
                        :class="[
                            'underline-offset-4 hover:underline',
                            closed && 'text-fg-muted line-through decoration-2',
                        ]"
                    >
                        {{ task.title }}
                    </RouterLink>
                </Heading>
                <Text v-if="showDescription && task.description" role="label" tone="muted" class="line-clamp-2">{{
                    task.description
                }}</Text>
            </div>
        </div>

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
                <Icon v-if="task.recurrence && !done" name="retry" :label="taskRecurrenceLabels[task.recurrence]" />
                <Text
                    v-if="task.checklist?.total"
                    as="span"
                    role="label"
                    tone="muted"
                    tabular
                    class="inline-flex items-center gap-1"
                    ><Icon
                        name="checklist"
                        :label="`Checklist: ${task.checklist.done} of ${task.checklist.total} done`"
                    />{{ task.checklist.done }}/{{ task.checklist.total }}</Text
                >
            </div>
            <div v-if="task.goal" class="flex min-w-0 items-center gap-2">
                <dt class="sr-only">Goal</dt>
                <ToneDot v-if="task.goal.tone" :tone="task.goal.tone" />
                <Text as="dd" role="label" tone="muted" truncate>{{ task.goal.title }}</Text>
            </div>
        </dl>

        <ProgressBar
            v-if="task.checklist?.total && !compact"
            :value="(task.checklist.done / task.checklist.total) * 100"
            :label="`Checklist progress for “${task.title}”`"
            tone="highlight"
        />

        <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3">
            <span class="flex items-center gap-2">
                <StatusBadge :status="task.status" />
                <Badge v-if="local" tone="info"><Icon name="offline" />Not synced yet</Badge>
            </span>
            <PriorityDots :priority="task.priority" />
        </div>
    </Card>
</template>
