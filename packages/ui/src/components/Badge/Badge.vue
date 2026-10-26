<script setup lang="ts">
import { computed } from 'vue';
import type { IconName } from '../../icons/registry';
import Icon from '../../primitives/Icon/Icon.vue';
import { useSurfaceTone } from '../../composables/useSurfaceTone';
import type { BadgeTone } from './tones';

const props = withDefaults(
    defineProps<{ tone?: BadgeTone; variant?: 'subtle' | 'outline'; icon?: IconName; dot?: boolean }>(),
    { tone: 'neutral', variant: 'subtle' },
);

const surface = useSurfaceTone();

const subtle: Record<BadgeTone, string> = {
    neutral: 'bg-surface-raised text-fg-muted ring-border',
    success: 'bg-success-subtle text-success-fg ring-success-border',
    warning: 'bg-warning-subtle text-warning-fg ring-warning-border',
    danger: 'bg-danger-subtle text-danger-fg ring-danger-border',
    info: 'bg-info-subtle text-info-fg ring-info-border',
    accent: 'bg-accent-solid text-accent-ink ring-border',
    highlight: 'bg-highlight text-highlight-ink ring-highlight',
    inverse: 'bg-surface-inverse text-fg-inverse ring-surface-inverse',
};

const outline: Record<BadgeTone, string> = {
    neutral: 'text-fg-muted ring-border-control',
    success: 'text-success-fg ring-success-border',
    warning: 'text-warning-fg ring-warning-border',
    danger: 'text-danger-fg ring-danger-border',
    info: 'text-info-fg ring-info-border',
    accent: 'text-accent-fg ring-accent',
    highlight: 'text-highlight-fg ring-highlight',
    inverse: 'text-fg ring-surface-inverse',
};

const classes = computed(() => {
    if (surface === 'highlight') {
        return props.variant === 'outline'
            ? 'ring-current text-highlight-ink'
            : 'bg-canvas text-highlight-fg ring-canvas';
    }
    return (props.variant === 'outline' ? outline : subtle)[props.tone];
});
</script>

<template>
    <span
        :class="[
            'inline-flex h-6 items-center gap-1 rounded-md px-2 text-caption font-medium whitespace-nowrap ring-1 ring-inset',
            classes,
        ]"
    >
        <span v-if="dot && !icon" class="size-2 rounded-full bg-current" aria-hidden="true" />
        <Icon v-if="icon" :name="icon" class="size-(--icon-sm)" />
        <slot />
    </span>
</template>
