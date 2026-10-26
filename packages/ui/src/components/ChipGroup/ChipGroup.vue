<script setup lang="ts" generic="T extends string">
/** A single-choice set of toggle chips, e.g. quick filters. */
defineProps<{ label: string; options: { value: T; label: string }[] }>();
const model = defineModel<T>({ required: true });
</script>

<template>
    <div role="group" :aria-label="label" class="flex flex-wrap gap-2">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="model === option.value"
            :class="[
                'h-(--control-sm) rounded-full px-4 text-label font-semibold transition-colors duration-(--duration-instant)',
                model === option.value
                    ? 'bg-surface-inverse text-fg-inverse'
                    : 'border border-border-control text-fg hover:border-border-strong',
            ]"
            @click="model = option.value"
        >
            {{ option.label }}
        </button>
    </div>
</template>
