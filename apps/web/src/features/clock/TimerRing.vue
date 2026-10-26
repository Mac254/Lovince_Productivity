<script setup lang="ts">
import { computed } from 'vue';

/** Circular progress: the track is the remaining time, the arc is time spent. */
const props = withDefaults(defineProps<{ progress: number; size?: number; stroke?: number; label: string }>(), {
    size: 320,
    stroke: 10,
});

const radius = computed(() => (props.size - props.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const offset = computed(() => circumference.value * (1 - Math.min(1, Math.max(0, props.progress))));
</script>

<template>
    <div class="relative inline-grid place-items-center" :style="{ width: `${size}px`, height: `${size}px` }">
        <svg
            :width="size"
            :height="size"
            :viewBox="`0 0 ${size} ${size}`"
            class="absolute inset-0 -rotate-90"
            role="img"
            :aria-label="label"
        >
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                class="stroke-chrome-border"
                :stroke-width="stroke"
                opacity="0.6"
            />
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                class="ring-arc stroke-highlight"
                :stroke-width="stroke"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="offset"
            />
        </svg>
        <div class="relative flex flex-col items-center"><slot /></div>
    </div>
</template>

<style scoped>
.ring-arc {
    transition: stroke-dashoffset var(--duration-slow) linear;
}
</style>
