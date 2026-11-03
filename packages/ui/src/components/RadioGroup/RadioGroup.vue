<script setup lang="ts" generic="T extends string">
import { LvRadioGroupItem, LvRadioGroupRoot } from '@lovince/ui-vue';
import { useId } from 'vue';

/**
 * One choice from a few, shown as pills, on Lovince's radio group (arrow keys move and select).
 * The `option` slot adds leading content, such as a colour dot. `cards` lays the choices out as a grid of previews.
 */
withDefaults(
    defineProps<{
        label: string;
        description?: string;
        options: { value: T; label: string }[];
        layout?: 'pills' | 'cards';
    }>(),
    { layout: 'pills' },
);
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
            :class="layout === 'cards' ? 'grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4' : 'flex flex-wrap gap-2'"
            @update:model-value="model = $event as T"
        >
            <LvRadioGroupItem
                v-for="option in options"
                :key="option.value"
                :value="option.value"
                :class="[
                    'cursor-pointer border border-border-control font-semibold transition-colors duration-(--duration-instant) hover:border-border-strong focus-visible:outline-2 focus-visible:outline-focus-ring state-checked:border-fg state-checked:bg-surface-raised',
                    layout === 'cards'
                        ? 'flex flex-col items-start gap-2 rounded-lg p-4 text-left text-caption text-fg-muted state-checked:text-fg state-checked:ring-1 state-checked:ring-fg'
                        : 'inline-flex items-center gap-2 rounded-full px-3 py-1 text-label',
                ]"
            >
                <slot name="option" :option="option" />
                {{ option.label }}
            </LvRadioGroupItem>
        </LvRadioGroupRoot>
    </div>
</template>
