<script setup lang="ts">
import { computed } from 'vue';
import type { IconName } from '../../icons/registry';
import type { RouteLocationRaw } from 'vue-router';
import Icon from '../../primitives/Icon/Icon.vue';
import { buttonVariants, controlHeights, interactive, type ButtonSize, type ButtonVariant } from './variants';

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
    <RouterLink v-if="to" :to="to" :class="classes">
        <Icon v-if="iconStart" :name="iconStart" motion="pop" />
        <slot />
        <Icon v-if="iconEnd" :name="iconEnd" motion="nudge" />
    </RouterLink>
    <button
        v-else
        :type="type"
        :class="classes"
        :disabled="disabled"
        :aria-disabled="loading || undefined"
        :aria-busy="loading || undefined"
        @click="loading && $event.preventDefault()"
    >
        <Icon v-if="loading" name="spinner" class="animate-spin" />
        <Icon v-else-if="iconStart" :name="iconStart" motion="pop" />
        <slot />
        <Icon v-if="iconEnd" :name="iconEnd" motion="nudge" />
    </button>
</template>
