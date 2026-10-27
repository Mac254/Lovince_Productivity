<script setup lang="ts">
import { computed } from 'vue';

/** The day as 24 hour cells: hours gone, the current hour filling up, hours still ahead. */
const props = defineProps<{ now: Date; hour12: boolean }>();

const hour = computed(() => props.now.getHours());
const minuteOfDay = computed(() => hour.value * 60 + props.now.getMinutes());
const hourFill = computed(() => (props.now.getMinutes() * 60 + props.now.getSeconds()) / 3600);
const left = computed(() => {
    const minutes = 24 * 60 - minuteOfDay.value;
    return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`;
});
const marks = computed(() =>
    [0, 6, 12, 18].map((h) =>
        props.hour12 ? `${h % 12 || 12} ${h < 12 ? 'AM' : 'PM'}` : `${String(h).padStart(2, '0')}:00`,
    ),
);
</script>

<template>
    <div
        role="img"
        :aria-label="`${Math.round((minuteOfDay / 1440) * 100)}% of the day has passed, ${left} left`"
        class="flex flex-col gap-2"
    >
        <div class="flex items-baseline justify-between gap-4 text-caption font-semibold tracking-wide uppercase">
            <span class="opacity-60">Day</span>
            <span class="tabular opacity-80">{{ left }} left</span>
        </div>
        <div class="grid grid-cols-24 gap-1" aria-hidden="true">
            <span
                v-for="h in 24"
                :key="h"
                :class="['hour h-2 overflow-hidden rounded-sm', h - 1 < hour && 'is-past', h - 1 === hour && 'is-now']"
            >
                <span v-if="h - 1 === hour" class="fill block h-full" :style="{ transform: `scaleX(${hourFill})` }" />
            </span>
        </div>
        <div class="grid grid-cols-4 text-caption tabular opacity-60" aria-hidden="true">
            <span v-for="mark in marks" :key="mark">{{ mark }}</span>
        </div>
    </div>
</template>

<style scoped>
.hour {
    background: color-mix(in oklab, currentColor 14%, transparent);
}

.is-past {
    background: color-mix(in oklab, currentColor 55%, transparent);
}

.fill {
    background: var(--highlight);
    transform-origin: left;
    transition: transform var(--duration-slower) linear;
}
</style>
