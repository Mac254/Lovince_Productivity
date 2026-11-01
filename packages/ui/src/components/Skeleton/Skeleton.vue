<script setup lang="ts">
import { LvSkeleton } from '@lovince/ui-vue';

/** Loading placeholder; size it to match the content it stands in for. */
withDefaults(defineProps<{ shape?: 'card' | 'pill' | 'line' }>(), { shape: 'card' });
</script>

<template>
    <LvSkeleton
        :shape="shape === 'line' ? 'text' : 'block'"
        :class="['animate-pulse bg-skeleton', { card: 'rounded-lg', pill: 'rounded-full', line: 'rounded-sm' }[shape]]"
    />
</template>
