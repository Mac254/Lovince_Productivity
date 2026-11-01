<script setup lang="ts">
import { LvProgressIndicator, LvProgressRoot } from '@lovince/ui-vue';
import { computed } from 'vue';

const props = withDefaults(defineProps<{ value: number; label: string; tone?: 'accent' | 'highlight' }>(), {
    tone: 'accent',
});

const clamped = computed(() => Math.min(100, Math.max(0, props.value)));
</script>

<template>
    <LvProgressRoot :value="clamped" :aria-label="label" class="h-2 w-full overflow-hidden rounded-full bg-border">
        <LvProgressIndicator
            class="h-full origin-left rounded-full transition-transform duration-(--duration-slower) ease-out"
            :class="tone === 'highlight' ? 'bg-highlight' : 'bg-accent'"
            :style="{ transform: `scaleX(${clamped / 100})` }"
        />
    </LvProgressRoot>
</template>
