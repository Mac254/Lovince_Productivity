<script setup lang="ts">
import { computed } from 'vue';
import { useSurfaceTone } from '../../composables/useSurfaceTone';

export type TextTone = 'default' | 'muted' | 'subtle' | 'accent' | 'highlight' | 'danger' | 'inverse';

const props = withDefaults(
    defineProps<{
        as?: string;
        role?: 'caption' | 'label' | 'body' | 'lead' | 'title';
        tone?: TextTone;
        weight?: 'medium' | 'semibold' | 'bold';
        tabular?: boolean;
        truncate?: boolean;
    }>(),
    { as: 'p', role: 'body', tone: 'default', weight: 'medium' },
);

const surface = useSurfaceTone();

const toneClass = computed(() => {
    if (surface === 'highlight') return 'text-highlight-ink';
    if (surface === 'accent') return 'text-accent-ink';
    return {
        default: 'text-fg',
        muted: 'text-fg-muted',
        subtle: 'text-fg-subtle',
        accent: 'text-accent-fg',
        highlight: 'text-highlight-fg',
        danger: 'text-danger-fg',
        inverse: 'text-fg-inverse',
    }[props.tone];
});
</script>

<template>
    <component
        :is="as"
        :class="[
            {
                caption: 'text-caption',
                label: 'text-label',
                body: 'text-body',
                lead: 'text-lead',
                title: 'text-title',
            }[role],
            { medium: 'font-medium', semibold: 'font-semibold', bold: 'font-bold' }[weight],
            toneClass,
            tabular && 'tabular',
            truncate && 'truncate',
        ]"
    >
        <slot />
    </component>
</template>
