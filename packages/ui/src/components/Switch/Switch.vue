<script setup lang="ts">
import { LvSwitchRoot, LvSwitchThumb } from '@lovince/ui-vue';

/** An on/off control that applies immediately. Name it with `label` or point `labelledby` at visible text. */
defineProps<{ label?: string; labelledby?: string; describedby?: string }>();
const model = defineModel<boolean>({ default: false });
</script>

<template>
    <LvSwitchRoot
        v-model="model"
        :aria-label="label"
        :aria-labelledby="labelledby"
        :aria-describedby="describedby"
        class="inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full bg-border-control transition-colors duration-(--duration-fast) state-checked:bg-highlight"
    >
        <LvSwitchThumb
            class="size-4 translate-x-1 rounded-full bg-surface transition-transform duration-(--duration-fast) ease-out state-checked:translate-x-6 state-checked:bg-highlight-ink"
        />
    </LvSwitchRoot>
</template>
