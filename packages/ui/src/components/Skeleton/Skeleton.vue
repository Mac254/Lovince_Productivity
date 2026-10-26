<script setup lang="ts">
/** Loading placeholder; size it to match the content it stands in for. */
withDefaults(defineProps<{ shape?: 'card' | 'pill' | 'line' }>(), { shape: 'card' });
</script>

<template>
    <div
        aria-hidden="true"
        :class="['animate-pulse bg-skeleton', { card: 'rounded-lg', pill: 'rounded-full', line: 'rounded-sm' }[shape]]"
    />
</template>
