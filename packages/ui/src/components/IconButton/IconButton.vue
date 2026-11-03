<script setup lang="ts">
import { LvButton } from '@lovince/ui-vue';
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import type { IconName } from '../../icons/registry';
import type { RouteLocationRaw } from 'vue-router';
import Icon, { type IconMotion } from '../../primitives/Icon/Icon.vue';
import Tooltip from '../Tooltip/Tooltip.vue';
import { ariaKeyshortcuts } from '../Tooltip/keyshortcuts';
import { buttonVariants, controlSquares, interactive, lovinceVariants, type ButtonSize } from '../Button/variants';

/** An icon-only button. `label` is required: it is the accessible name and the tooltip, with `shortcut` beside it. */
defineOptions({ inheritAttrs: false });
withDefaults(
    defineProps<{
        icon: IconName;
        label: string;
        shortcut?: string;
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

const button = ref<{ $el: HTMLElement } | null>(null);

defineExpose({ focus: () => button.value?.$el.focus() });
</script>

<template>
    <Tooltip :label="label" :shortcut="shortcut">
        <LvButton
            ref="button"
            v-bind="$attrs"
            :as="to ? RouterLink : 'button'"
            :to="to"
            size="icon"
            :variant="lovinceVariants[variant]"
            :disabled="to ? undefined : disabled"
            :aria-label="label"
            :aria-keyshortcuts="ariaKeyshortcuts(shortcut)"
            :aria-pressed="pressed"
            :aria-current="current ? 'page' : undefined"
            :class="[
                interactive,
                controlSquares[size],
                buttonVariants[variant],
                current && 'border-2 border-highlight text-highlight-fg',
            ]"
        >
            <slot><Icon :name="icon" :size="size === 'lg' ? 'md' : 'sm'" :motion="motion" /></slot>
        </LvButton>
    </Tooltip>
</template>
