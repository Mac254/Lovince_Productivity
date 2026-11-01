<script setup lang="ts" generic="T extends string">
import { LvToggleGroupItem, LvToggleGroupRoot } from '@lovince/ui-vue';

/** A single-choice set of toggle chips, e.g. quick filters. Arrow keys move between chips; one is always chosen. */
defineProps<{ label: string; options: { value: T; label: string }[] }>();
const model = defineModel<T>({ required: true });

function choose(value: string | string[] | undefined): void {
    if (typeof value === 'string') model.value = value as T;
}
</script>

<template>
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
            :class="[
                'h-(--control-sm) rounded-full px-4 text-label font-semibold transition-colors duration-(--duration-instant)',
                'border border-border-control text-fg hover:border-border-strong',
                'state-on:border-transparent state-on:bg-surface-inverse state-on:text-fg-inverse',
            ]"
        >
            {{ option.label }}
        </LvToggleGroupItem>
    </LvToggleGroupRoot>
</template>
