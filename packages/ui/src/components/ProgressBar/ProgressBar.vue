<script setup lang="ts">
withDefaults(defineProps<{ value: number; label: string; tone?: 'accent' | 'highlight' }>(), { tone: 'accent' });
</script>

<template>
    <div
        role="progressbar"
        :aria-label="label"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="value"
        class="h-2 w-full overflow-hidden rounded-full bg-border"
    >
        <div
            class="h-full origin-left rounded-full transition-transform duration-(--duration-slower) ease-out"
            :class="tone === 'highlight' ? 'bg-highlight' : 'bg-accent'"
            :style="{ transform: `scaleX(${Math.min(100, Math.max(0, value)) / 100})` }"
        />
    </div>
</template>
