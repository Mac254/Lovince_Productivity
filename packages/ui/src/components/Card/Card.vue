<script setup lang="ts">
import { provideSurfaceTone } from '../../composables/useSurfaceTone';

/**
 * The surface for grouped content (pair with CardHeader). Children read the surface tone,
 * so text and badges stay legible on highlight and accent cards. `padding="none"` is for flush
 * content such as an editor, which then pads its own regions.
 */
const props = withDefaults(
    defineProps<{
        as?: string;
        tone?: 'default' | 'highlight' | 'accent';
        padding?: 'none' | 'md' | 'lg';
        interactive?: boolean;
    }>(),
    { as: 'article', tone: 'default', padding: 'md' },
);

provideSurfaceTone(props.tone);
</script>

<template>
    <component
        :is="as"
        :class="[
            'relative flex min-w-0 flex-col rounded-lg transition-shadow duration-(--duration-fast)',
            { none: 'overflow-hidden', md: 'p-4 sm:p-6', lg: 'p-6 sm:p-8' }[padding],
            {
                default: 'bg-surface text-fg ring-1 ring-border',
                highlight: 'bg-highlight text-highlight-ink',
                accent: 'bg-accent-solid text-accent-ink',
            }[tone],
            interactive && tone === 'default' && 'hover:ring-border-strong',
        ]"
    >
        <slot />
    </component>
</template>
