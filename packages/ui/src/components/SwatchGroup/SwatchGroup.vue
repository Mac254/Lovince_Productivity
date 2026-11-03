<script setup lang="ts" generic="T extends string">
import { LvRadioGroupItem, LvRadioGroupRoot } from '@lovince/ui-vue';
import Icon from '../../primitives/Icon/Icon.vue';
import Tooltip from '../Tooltip/Tooltip.vue';

defineProps<{ label: string; options: { value: T; label: string; color: string }[] }>();
const model = defineModel<T>({ required: true });

function choose(value: string): void {
    model.value = value as T;
}
</script>

<template>
    <LvRadioGroupRoot
        :model-value="model"
        :aria-label="label"
        orientation="horizontal"
        class="flex flex-wrap gap-3"
        @update:model-value="choose"
    >
        <Tooltip v-for="option in options" :key="option.value" :label="option.label">
            <LvRadioGroupItem
                :value="option.value"
                :aria-label="option.label"
                class="group inline-flex size-(--control-sm) cursor-pointer items-center justify-center rounded-full text-highlight-ink ring-2 ring-transparent ring-offset-2 ring-offset-surface transition-transform duration-(--duration-fast) hover:scale-105 focus-visible:outline-2 focus-visible:outline-focus-ring aria-checked:ring-fg"
                :style="{ backgroundColor: option.color }"
            >
                <Icon name="check" size="sm" class="opacity-0 group-aria-checked:opacity-100" />
            </LvRadioGroupItem>
        </Tooltip>
    </LvRadioGroupRoot>
</template>
