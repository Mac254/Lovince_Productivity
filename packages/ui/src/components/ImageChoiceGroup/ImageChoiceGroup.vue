<script setup lang="ts" generic="T extends string">
import { LvToggleGroupItem, LvToggleGroupRoot } from '@lovince/ui-vue';

defineProps<{ label: string; options: { value: T; label: string; url: string }[] }>();
const model = defineModel<T | ''>({ required: true });

function choose(value: string | string[] | undefined): void {
    if (typeof value === 'string' && value) model.value = value as T;
}
</script>

<template>
    <LvToggleGroupRoot
        type="single"
        :model-value="model"
        :allow-empty="false"
        role="group"
        :aria-label="label"
        class="grid grid-cols-3 gap-2 sm:grid-cols-4"
        @update:model-value="choose"
    >
        <LvToggleGroupItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            :aria-label="option.label"
            class="aspect-video rounded-md border-2 border-transparent bg-surface-raised bg-cover bg-center transition-transform duration-(--duration-fast) hover:scale-105 focus-visible:outline-2 focus-visible:outline-focus-ring state-on:border-highlight"
            :style="{ backgroundImage: `url(&quot;${option.url}&quot;)` }"
        />
        <slot />
    </LvToggleGroupRoot>
</template>
