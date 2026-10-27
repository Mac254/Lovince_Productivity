<script setup lang="ts">
import { computed } from 'vue';
import { dayProgressLabel, type DayProgress } from '@/lib/progress';

/** Today's plan as one bar: done in full colour, worked on at half weight in a lighter tint, the rest to do. */
const props = defineProps<{ day: DayProgress }>();

const share = (count: number) => (props.day.total ? (count / props.day.total) * 100 : 0);
const toDo = computed(() => props.day.remaining - props.day.worked);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div
            role="progressbar"
            aria-label="Today’s progress"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="day.percent"
            :aria-valuetext="`${day.percent}%, ${dayProgressLabel(day)}`"
            class="flex h-2 w-full overflow-hidden rounded-full bg-border"
        >
            <div class="segment h-full bg-highlight" :style="{ width: `${share(day.done)}%` }" />
            <div class="segment worked h-full" :style="{ width: `${share(day.worked / 2)}%` }" />
        </div>
        <ul v-if="day.total" class="flex flex-wrap gap-x-4 gap-y-1 text-caption text-fg-muted" aria-hidden="true">
            <li class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-highlight" />{{ day.done }} done
            </li>
            <li v-if="day.worked" class="flex items-center gap-2">
                <span class="worked size-2 rounded-full" />{{ day.worked }} worked on, counts half
            </li>
            <li v-if="toDo" class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-border" />{{ toDo }} to do
            </li>
        </ul>
    </div>
</template>

<style scoped>
.segment {
    transition: width var(--duration-slower) var(--ease-out);
}

.worked {
    background: color-mix(in oklab, var(--highlight) 45%, var(--border));
}
</style>
