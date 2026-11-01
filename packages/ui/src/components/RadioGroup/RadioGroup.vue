<script setup lang="ts" generic="T extends string">
import { LvRadioGroupItem, LvRadioGroupRoot } from '@lovince/ui-vue';
import { useId } from 'vue';

/**
 * One choice from a few, shown as pills, on Lovince's radio group (arrow keys move and select).
 * The `option` slot adds leading content, such as a colour dot.
 */
defineProps<{ label: string; description?: string; options: { value: T; label: string }[] }>();
const model = defineModel<T>({ required: true });
const labelId = useId();
const descriptionId = useId();
</script>

<template>
    <div class="flex flex-col gap-2">
        <span :id="labelId" class="mb-1 text-label font-semibold">{{ label }}</span>
        <p v-if="description" :id="descriptionId" class="text-label text-fg-muted">{{ description }}</p>
        <LvRadioGroupRoot
            :model-value="model"
            :aria-labelledby="labelId"
            :aria-describedby="description ? descriptionId : undefined"
            class="flex flex-wrap gap-2"
            @update:model-value="model = $event as T"
        >
            <LvRadioGroupItem
                v-for="option in options"
                :key="option.value"
                :value="option.value"
                class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-border-control px-3 py-1 text-label font-semibold transition-colors duration-(--duration-instant) hover:border-border-strong focus-visible:outline-2 focus-visible:outline-focus-ring state-checked:border-fg state-checked:bg-surface-raised"
            >
                <slot name="option" :option="option" />
                {{ option.label }}
            </LvRadioGroupItem>
        </LvRadioGroupRoot>
    </div>
</template>
