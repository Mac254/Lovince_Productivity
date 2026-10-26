<script setup lang="ts">
import { computed } from 'vue';
import type { IconName } from '../../icons/registry';
import Icon from '../../primitives/Icon/Icon.vue';
import { useSurfaceTone } from '../../composables/useSurfaceTone';
import type { BadgeTone } from './tones';

const props = withDefaults(defineProps<{ tone?: BadgeTone; variant?: 'solid' | 'outline'; icon?: IconName }>(), {
    tone: 'neutral',
    variant: 'solid',
});

const surface = useSurfaceTone();

const solid: Record<BadgeTone, string> = {
    neutral: 'bg-surface-raised text-fg-muted',
    accent: 'bg-accent-solid text-accent-ink',
    highlight: 'bg-highlight text-highlight-ink',
    inverse: 'bg-surface-inverse text-fg-inverse',
};

const outline: Record<BadgeTone, string> = {
    neutral: 'border border-border-control text-fg',
    accent: 'border border-accent text-accent-fg',
    highlight: 'border border-highlight text-highlight-fg',
    inverse: 'border border-surface-inverse text-fg',
};

const classes = computed(() => {
    if (surface === 'highlight') {
        return props.variant === 'outline' ? 'border border-current text-highlight-ink' : 'bg-canvas text-highlight-fg';
    }
    return props.variant === 'outline' ? outline[props.tone] : solid[props.tone];
});
</script>

<template>
    <span :class="['inline-flex items-center gap-2 rounded-full px-3 py-1 text-caption font-semibold', classes]">
        <Icon v-if="icon" :name="icon" class="size-(--icon-sm)" />
        <slot />
    </span>
</template>
