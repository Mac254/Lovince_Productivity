<script setup lang="ts" generic="T extends string">
import { useLiquid } from '@focus/motion';
import { LvToggleGroupItem, LvToggleGroupRoot } from '@lovince/ui-vue';
import { ref } from 'vue';

/** A single-choice set of toggle chips, e.g. quick filters. Arrow keys move between chips; one is always chosen. */
defineProps<{ label: string; options: { value: T; label: string }[] }>();
const model = defineModel<T>({ required: true });

const group = ref<HTMLElement | null>(null);
useLiquid(group);

function choose(value: string | string[] | undefined): void {
    if (typeof value === 'string') model.value = value as T;
}
</script>

<template>
    <div ref="group" data-liquid class="chips w-fit max-w-full">
        <span data-liquid-indicator aria-hidden="true" class="bg-surface-inverse" />
        <LvToggleGroupRoot
            type="single"
            :model-value="model"
            :allow-empty="false"
            role="group"
            :aria-label="label"
            class="flex flex-wrap gap-2"
            @update:model-value="choose"
        >
            <LvToggleGroupItem
                v-for="option in options"
                :key="option.value"
                :value="option.value"
                data-liquid-item
                :class="[
                    'h-(--control-sm) rounded-full px-4 text-label font-semibold transition-colors duration-(--duration-fast)',
                    'border border-border-control text-fg hover:border-border-strong',
                    'focus-visible:outline-2 focus-visible:outline-focus-ring',
                    'state-on:border-transparent state-on:text-fg-inverse',
                ]"
            >
                {{ option.label }}
            </LvToggleGroupItem>
        </LvToggleGroupRoot>
    </div>
</template>

<style scoped>
.chips {
    --liquid-radius: var(--radius-full);
}
</style>
