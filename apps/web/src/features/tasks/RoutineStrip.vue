<script setup lang="ts">
import type { Task } from '@focus/types';
import type { Routine, RoutineDay } from './routines';

/** Today's repeated tasks as one row of check-pills, each with its last seven days as dots. */
const props = defineProps<{ routines: Routine[] }>();
const emit = defineEmits<{ toggle: [task: Task] }>();

const done = computed(() => props.routines.filter((routine) => routine.done).length);

const dayTone: Record<RoutineDay, string> = {
    done: 'bg-highlight',
    missed: 'bg-danger',
    open: 'bg-border-strong',
    none: 'bg-border',
};
const dayWord: Record<RoutineDay, string> = { done: 'done', missed: 'missed', open: 'due', none: 'nothing due' };

function history(routine: Routine): string {
    const shown = routine.days.filter((day) => day !== 'none');
    const kept = shown.filter((day) => day === 'done').length;
    const missed = shown.filter((day) => day === 'missed').length;
    return `Last 7 days: ${kept} ${dayWord.done}, ${missed} ${dayWord.missed}`;
}
</script>

<template>
    <section aria-labelledby="routines-title" class="flex flex-col gap-3">
        <header class="flex items-center gap-2">
            <Icon name="retry" class="text-fg-muted" />
            <Text id="routines-title" as="h2" role="label" weight="bold">Routines today</Text>
            <Text as="span" role="caption" tone="muted" tabular>{{ done }} of {{ routines.length }}</Text>
        </header>
        <ul class="flex flex-wrap gap-2">
            <li
                v-for="routine in routines"
                :key="routine.key"
                :class="[
                    'flex min-w-0 max-w-full items-center gap-2 rounded-full border border-border bg-surface py-1 pr-3 pl-1 transition-colors duration-(--duration-instant)',
                    routine.done && 'bg-surface-raised',
                ]"
            >
                <TaskCheck :task="routine.task" @toggle="emit('toggle', routine.task)" />
                <ToneDot v-if="routine.task.goal?.tone" :tone="routine.task.goal.tone" />
                <RouterLink
                    v-if="!isLocal(routine.task)"
                    :to="{ name: 'task-detail', params: { id: routine.task.id } }"
                    :class="[
                        'min-w-0 truncate text-label font-semibold underline-offset-4 hover:underline',
                        routine.done && 'text-fg-muted line-through',
                    ]"
                    >{{ routine.task.title }}</RouterLink
                >
                <span v-else class="min-w-0 truncate text-label font-semibold">{{ routine.task.title }}</span>
                <span class="flex shrink-0 items-center gap-1" role="img" :aria-label="history(routine)">
                    <span
                        v-for="(day, index) in routine.days"
                        :key="index"
                        :class="['size-1.5 rounded-full', dayTone[day]]"
                    />
                </span>
            </li>
        </ul>
    </section>
</template>
