<script setup lang="ts">
import type { Dashboard } from '@focus/types';
import { Icon } from '@focus/ui';
import { computed } from 'vue';
import { dueLabel } from '@/lib/dates';
import { dayProgress, dayProgressLabel } from '@/lib/progress';
import { useTimerStore } from '@/stores/timer';

/** Today at a glance under the clock: the plan's progress, the next task, and the focus timer. */
const props = defineProps<{ data: Dashboard | null; error: string | null }>();
defineEmits<{ retry: [] }>();

const timer = useTimerStore();
const day = computed(() => (props.data ? dayProgress(props.data.stats) : null));
const next = computed(() => props.data?.planned_tasks[0] ?? props.data?.overdue_tasks[0] ?? null);
const nextMeta = computed(() => {
    if (!next.value) return '';
    if (next.value.is_overdue || next.value.due_date) return dueLabel(next.value.due_date);
    return next.value.is_highlighted ? 'Starred for today' : '';
});
</script>

<template>
    <section v-if="data && day" aria-label="Today" class="strip grid w-full text-left sm:grid-cols-3">
        <div class="flex flex-col gap-2 py-4 sm:py-1 sm:pr-6">
            <p class="text-caption font-semibold tracking-wide uppercase opacity-60">Today’s plan</p>
            <template v-if="day.total">
                <p class="font-display text-title font-semibold tabular">{{ day.percent }}%</p>
                <div
                    role="progressbar"
                    aria-label="Today’s progress"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-valuenow="day.percent"
                    class="track h-1 overflow-hidden rounded-full"
                >
                    <div class="fill h-full" :style="{ transform: `scaleX(${day.percent / 100})` }" />
                </div>
                <p class="text-label tabular opacity-80">{{ dayProgressLabel(day) }}</p>
            </template>
            <template v-else>
                <p class="font-display text-title font-semibold">Open</p>
                <p class="text-label opacity-80">Star a task or set it due today to plan your day.</p>
            </template>
        </div>

        <div class="flex min-w-0 flex-col gap-2 py-4 sm:px-6 sm:py-1">
            <p class="text-caption font-semibold tracking-wide uppercase opacity-60">Next up</p>
            <template v-if="next">
                <RouterLink
                    :to="{ name: 'task-detail', params: { id: next.id } }"
                    class="line-clamp-2 text-lead font-semibold underline-offset-4 hover:underline"
                    >{{ next.title }}</RouterLink
                >
                <p v-if="nextMeta" :class="['text-label', next.is_overdue ? 'font-semibold' : 'opacity-80']">
                    {{ nextMeta }}
                </p>
            </template>
            <p v-else class="text-lead font-semibold">All clear</p>
        </div>

        <div class="flex flex-col items-start gap-2 py-4 sm:py-1 sm:pl-6">
            <p class="text-caption font-semibold tracking-wide uppercase opacity-60">Focus</p>
            <RouterLink
                :to="{ query: { mode: 'timer' } }"
                class="group inline-flex h-(--control-md) items-center gap-2 rounded-full bg-highlight px-6 text-label font-semibold text-highlight-ink transition-colors hover:bg-highlight-hover"
            >
                <Icon name="clock" motion="spin" />
                {{ timer.active ? `${timer.display} left` : 'Start a session' }}
            </RouterLink>
            <p v-if="timer.task && timer.active" class="max-w-full truncate text-label opacity-80">
                {{ timer.task.title }}
            </p>
        </div>
    </section>

    <p v-else-if="error" class="text-label opacity-80">
        Today’s plan didn’t load.
        <button type="button" class="font-semibold underline underline-offset-4" @click="$emit('retry')">
            Try again
        </button>
    </p>
</template>

<style scoped>
.strip {
    border-block: 1px solid color-mix(in oklab, currentColor 18%, transparent);
}

.strip > * + * {
    border-top: 1px solid color-mix(in oklab, currentColor 18%, transparent);
}

@media (width >= 40rem) {
    .strip {
        padding-block: calc(var(--spacing) * 6);
    }

    .strip > * + * {
        border-top: 0;
        border-left: 1px solid color-mix(in oklab, currentColor 18%, transparent);
    }
}

.track {
    background: color-mix(in oklab, currentColor 18%, transparent);
}

.fill {
    background: var(--highlight);
    transform-origin: left;
    transition: transform var(--duration-slower) var(--ease-out);
}
</style>
