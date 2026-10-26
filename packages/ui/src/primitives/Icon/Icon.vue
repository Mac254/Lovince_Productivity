<script setup lang="ts">
import { icons, type IconName } from '../../icons/registry';

export type IconMotion = 'none' | 'pop' | 'nudge' | 'nudge-back' | 'lift' | 'spin' | 'wiggle' | 'enter';

/**
 * Icons inherit text colour and are decorative unless given a `label`.
 * `motion` animates the icon when its `group` parent is hovered or focused.
 */
withDefaults(defineProps<{ name: IconName; size?: 'sm' | 'md' | 'lg'; label?: string; motion?: IconMotion }>(), {
    size: 'sm',
    motion: 'none',
});
</script>

<template>
    <component
        :is="icons[name]"
        :class="[
            'shrink-0',
            { sm: 'size-(--icon-sm)', md: 'size-(--icon-md)', lg: 'size-(--icon-lg)' }[size],
            motion !== 'none' && `motion-${motion}`,
        ]"
        :aria-hidden="label ? undefined : 'true'"
        :aria-label="label"
        :role="label ? 'img' : undefined"
        focusable="false"
    />
</template>
