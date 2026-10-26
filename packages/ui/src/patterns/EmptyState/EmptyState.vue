<script setup lang="ts">
import type { IconName } from '../../icons/registry';
import Icon from '../../primitives/Icon/Icon.vue';

/** Explains what would be here; the default slot holds the action that creates it. */
defineProps<{ icon: IconName; title: string; message?: string }>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border-control px-6 py-12 text-center"
    >
        <span
            class="mb-2 inline-flex size-(--control-lg) items-center justify-center rounded-full bg-surface-raised text-highlight-fg"
        >
            <Icon :name="icon" size="md" />
        </span>
        <p class="font-display text-lead font-medium">{{ title }}</p>
        <p v-if="message" class="max-w-form text-label text-fg-muted">{{ message }}</p>
        <div v-if="$slots.default" class="mt-4"><slot /></div>
    </div>
</template>
