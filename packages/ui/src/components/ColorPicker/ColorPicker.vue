<script setup lang="ts">
import {
    LvColorPickerArea,
    LvColorPickerInput,
    LvColorPickerRoot,
    LvColorPickerSlider,
    LvColorPickerSwatch,
} from '@lovince/ui-vue';
import { useId } from 'vue';
import Tooltip from '../Tooltip/Tooltip.vue';

/** Any colour from a saturation area, a hue slider and a hex field, with optional preset swatches. */
defineProps<{ label: string; description?: string; presets?: { value: string; label: string }[] }>();
const model = defineModel<string>({ required: true });
const labelId = useId();
</script>

<template>
    <div role="group" :aria-labelledby="labelId" class="flex flex-col gap-3">
        <span :id="labelId" class="sr-only">{{ label }}</span>
        <LvColorPickerRoot v-model="model" class="flex flex-col gap-3">
            <LvColorPickerArea
                class="color-area h-36 w-full cursor-crosshair rounded-md ring-1 ring-border ring-inset"
                label="Saturation and brightness"
            />
            <LvColorPickerSlider channel="hue" class="color-hue h-3 w-full cursor-pointer rounded-full" />
            <div class="flex flex-wrap items-center gap-3">
                <span
                    class="size-(--control-sm) shrink-0 rounded-md ring-1 ring-border"
                    :style="{ backgroundColor: model }"
                />
                <LvColorPickerInput
                    :label="`${label} hex value`"
                    class="h-(--control-sm) w-28 rounded-md border border-border-control bg-surface px-3 font-mono text-label uppercase focus-visible:outline-2 focus-visible:outline-focus-ring data-invalid:border-danger"
                />
                <div v-if="presets?.length" class="flex flex-wrap gap-2">
                    <Tooltip v-for="preset in presets" :key="preset.value" :label="preset.label">
                        <LvColorPickerSwatch
                            :value="preset.value"
                            :label="preset.label"
                            :style="{ backgroundColor: preset.value }"
                            :class="[
                                'size-(--icon-lg) cursor-pointer rounded-full ring-offset-2 ring-offset-surface transition-shadow duration-(--duration-fast) focus-visible:outline-2 focus-visible:outline-focus-ring',
                                preset.value.toLowerCase() === model.toLowerCase()
                                    ? 'ring-2 ring-fg'
                                    : 'hover:ring-2 hover:ring-border-strong',
                            ]"
                        />
                    </Tooltip>
                </div>
            </div>
        </LvColorPickerRoot>
    </div>
</template>

<style scoped>
.color-area {
    background-image: linear-gradient(to top, black, transparent), linear-gradient(to right, white, transparent);
}

.color-hue {
    background-image: linear-gradient(to right in hsl longer hue, red 0 100%);
}

.color-area :deep([data-color-thumb]),
.color-hue :deep([data-color-thumb]) {
    width: var(--icon-md);
    height: var(--icon-md);
    border-radius: var(--radius-full);
    border: 2px solid white;
    box-shadow: var(--shadow-floating);
    background: var(--lovince-color);
}

.color-hue :deep([data-color-thumb]) {
    top: 50%;
    translate: 0 -50%;
}

.color-area :deep([data-color-thumb]:focus-visible),
.color-hue :deep([data-color-thumb]:focus-visible) {
    outline: 2px solid var(--focus-ring);
    outline-offset: 2px;
}
</style>
