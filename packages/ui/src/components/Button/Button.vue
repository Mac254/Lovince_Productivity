<script setup lang="ts">
import { LvButton } from '@lovince/ui-vue';
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import type { IconName } from '../../icons/registry';
import type { RouteLocationRaw } from 'vue-router';
import AnimatedIcon from '../../primitives/AnimatedIcon/AnimatedIcon.vue';
import Icon from '../../primitives/Icon/Icon.vue';
import {
    buttonVariants,
    controlHeights,
    interactive,
    lovinceVariants,
    type ButtonSize,
    type ButtonVariant,
} from './variants';

const props = withDefaults(
    defineProps<{
        variant?: ButtonVariant;
        size?: ButtonSize;
        type?: 'button' | 'submit';
        iconStart?: IconName;
        iconEnd?: IconName;
        loading?: boolean;
        disabled?: boolean;
        to?: RouteLocationRaw;
    }>(),
    { variant: 'primary', size: 'md', type: 'button' },
);

const classes = computed(() => [
    interactive,
    controlHeights[props.size],
    buttonVariants[props.variant],
    props.size === 'sm' ? 'gap-2 px-4 text-label' : 'gap-2 px-6 text-body',
]);
</script>

<template>
    <LvButton
        :as="to ? RouterLink : 'button'"
        :to="to"
        :type="type"
        :variant="lovinceVariants[variant]"
        :size="size"
        :loading="loading"
        :disabled="disabled"
        :class="classes"
    >
        <AnimatedIcon v-if="loading" kind="loading" />
        <Icon v-else-if="iconStart" :name="iconStart" motion="pop" />
        <slot />
        <Icon v-if="iconEnd" :name="iconEnd" motion="nudge" />
    </LvButton>
</template>
