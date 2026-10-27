<script setup lang="ts">
/** An on/off control that applies immediately. Name it with `label` or point `labelledby` at visible text. */
defineProps<{ label?: string; labelledby?: string; describedby?: string }>();
const model = defineModel<boolean>({ default: false });
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="model"
        :aria-label="label"
        :aria-labelledby="labelledby"
        :aria-describedby="describedby"
        :class="[
            'inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-(--duration-fast)',
            model ? 'bg-highlight' : 'bg-border-control',
        ]"
        @click="model = !model"
    >
        <span
            aria-hidden="true"
            :class="[
                'size-4 rounded-full transition-transform duration-(--duration-fast) ease-out',
                model ? 'translate-x-6 bg-highlight-ink' : 'translate-x-1 bg-surface',
            ]"
        />
    </button>
</template>
