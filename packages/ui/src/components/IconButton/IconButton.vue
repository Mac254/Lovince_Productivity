<script setup lang="ts">
import type { IconName } from '../../icons/registry';
import type { RouteLocationRaw } from 'vue-router';
import Icon, { type IconMotion } from '../../primitives/Icon/Icon.vue';
import { buttonVariants, controlSquares, interactive, type ButtonSize } from '../Button/variants';

/** An icon-only button. `label` is required: it is the accessible name and the tooltip. */
withDefaults(
    defineProps<{
        icon: IconName;
        label: string;
        variant?: 'outline' | 'secondary' | 'primary' | 'ghost';
        size?: ButtonSize;
        to?: RouteLocationRaw;
        pressed?: boolean;
        current?: boolean;
        disabled?: boolean;
        motion?: IconMotion;
    }>(),
    { variant: 'outline', size: 'md', pressed: undefined, motion: 'pop' },
);
</script>

<template>
    <component
        :is="to ? 'RouterLink' : 'button'"
        :to="to"
        :type="to ? undefined : 'button'"
        :aria-label="label"
        :title="label"
        :aria-pressed="pressed"
        :aria-current="current ? 'page' : undefined"
        :disabled="to ? undefined : disabled"
        :class="[
            interactive,
            controlSquares[size],
            buttonVariants[variant],
            current && 'border-2 border-highlight text-highlight-fg',
        ]"
    >
        <Icon :name="icon" :size="size === 'lg' ? 'md' : 'sm'" :motion="motion" />
    </component>
</template>
