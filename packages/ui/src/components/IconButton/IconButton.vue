<script setup lang="ts">
import { LvButton } from '@lovince/ui-vue';
import { RouterLink } from 'vue-router';
import type { IconName } from '../../icons/registry';
import type { RouteLocationRaw } from 'vue-router';
import Icon, { type IconMotion } from '../../primitives/Icon/Icon.vue';
import { buttonVariants, controlSquares, interactive, lovinceVariants, type ButtonSize } from '../Button/variants';

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
    <LvButton
        :as="to ? RouterLink : 'button'"
        :to="to"
        size="icon"
        :variant="lovinceVariants[variant]"
        :disabled="to ? undefined : disabled"
        :aria-label="label"
        :title="label"
        :aria-pressed="pressed"
        :aria-current="current ? 'page' : undefined"
        :class="[
            interactive,
            controlSquares[size],
            buttonVariants[variant],
            current && 'border-2 border-highlight text-highlight-fg',
        ]"
    >
        <Icon :name="icon" :size="size === 'lg' ? 'md' : 'sm'" :motion="motion" />
    </LvButton>
</template>
