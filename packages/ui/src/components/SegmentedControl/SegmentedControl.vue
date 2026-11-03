<script setup lang="ts" generic="T extends string">
import { useLiquid } from '@focus/motion';
import { LvSegmentedControlItem, LvSegmentedControlRoot } from '@lovince/ui-vue';
import { ref } from 'vue';
import type { IconName } from '../../icons/registry';
import Icon from '../../primitives/Icon/Icon.vue';
import Tooltip from '../Tooltip/Tooltip.vue';

withDefaults(
    defineProps<{
        label: string;
        options: { value: T; label: string; icon?: IconName }[];
        iconOnly?: boolean;
        size?: 'sm' | 'md';
    }>(),
    { size: 'md' },
);
const model = defineModel<T>({ required: true });

const root = ref<HTMLElement | null>(null);
useLiquid(root);

function choose(value: string): void {
    model.value = value as T;
}
</script>

<template>
    <div
        ref="root"
        data-liquid
        class="segmented relative inline-flex w-fit max-w-full rounded-full bg-surface-raised p-1 ring-1 ring-border"
    >
        <span data-liquid-indicator aria-hidden="true" class="bg-highlight" />
        <LvSegmentedControlRoot
            :model-value="model"
            :aria-label="label"
            class="relative flex gap-1"
            @update:model-value="choose"
        >
            <Tooltip v-for="option in options" :key="option.value" :label="option.label" :disabled="!iconOnly">
                <LvSegmentedControlItem
                    :value="option.value"
                    :aria-label="iconOnly ? option.label : undefined"
                    data-liquid-item
                    :class="[
                        'inline-flex cursor-pointer items-center justify-center gap-2 rounded-full font-semibold text-fg-muted transition-colors duration-(--duration-fast) hover:text-fg',
                        'focus-visible:outline-2 focus-visible:outline-focus-ring',
                        'aria-checked:text-highlight-ink',
                        size === 'sm' ? 'h-8 text-caption' : 'h-(--control-sm) text-label',
                        iconOnly ? (size === 'sm' ? 'w-8' : 'w-(--control-sm)') : 'px-4',
                    ]"
                >
                    <Icon v-if="option.icon" :name="option.icon" size="sm" />
                    <span v-if="!iconOnly">{{ option.label }}</span>
                </LvSegmentedControlItem>
            </Tooltip>
        </LvSegmentedControlRoot>
    </div>
</template>

<style scoped>
.segmented {
    --liquid-radius: var(--radius-full);
}
</style>
