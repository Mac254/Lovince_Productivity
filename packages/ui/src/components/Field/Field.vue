<script setup lang="ts">
import { LvFieldLabel, LvFieldRoot } from '@lovince/ui-vue';
import type { ControlSurface } from './controlClasses';

/**
 * Label, hint and error wrapper for any control, on Lovince's field context: Lovince inputs pick up
 * the id, label and invalid state on their own. The slot still receives `describedBy` and `invalid`
 * for controls outside that context, such as the select trigger.
 */
defineProps<{
    id: string;
    label: string;
    error?: string;
    hint?: string;
    optional?: boolean;
    surface?: ControlSurface;
}>();
</script>

<template>
    <LvFieldRoot :id="id" :invalid="!!error" class="flex flex-col gap-2">
        <LvFieldLabel :class="['text-label font-semibold', surface === 'chrome' ? 'text-chrome-fg' : 'text-fg']">
            {{ label }}
            <span v-if="optional" class="font-medium text-fg-muted">(optional)</span>
        </LvFieldLabel>
        <slot :described-by="error ? `${id}-error` : hint ? `${id}-hint` : undefined" :invalid="!!error" />
        <p v-if="error" :id="`${id}-error`" class="text-label text-danger-fg">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="text-label text-fg-muted">{{ hint }}</p>
    </LvFieldRoot>
</template>
