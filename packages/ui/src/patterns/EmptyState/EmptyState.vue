<script setup lang="ts">
import { LvEmptyState } from '@lovince/ui-vue';
import type { IconName } from '../../icons/registry';
import Icon from '../../primitives/Icon/Icon.vue';

/** Explains what would be here; the default slot holds the action that creates it. */
defineProps<{ icon: IconName; title: string; message?: string }>();
</script>

<template>
    <LvEmptyState
        class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border-control px-6 py-12 text-center"
    >
        <template #icon>
            <span
                class="mb-2 inline-flex size-(--control-lg) items-center justify-center rounded-full bg-surface-raised text-highlight-fg"
            >
                <Icon :name="icon" size="md" />
            </span>
        </template>
        <template #title>
            <span class="font-display text-lead font-medium">{{ title }}</span>
        </template>
        <template v-if="message" #description>
            <span class="block max-w-form text-label text-fg-muted">{{ message }}</span>
        </template>
        <template v-if="$slots.default" #actions>
            <div class="mt-4"><slot /></div>
        </template>
    </LvEmptyState>
</template>
