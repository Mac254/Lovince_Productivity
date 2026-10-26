<script setup lang="ts">
import type { IconName } from '../../icons/registry';
import Icon from '../../primitives/Icon/Icon.vue';

/** Title row for a card: optional icon chip, title, subtitle, and actions on the right. */
defineProps<{ title: string; subtitle?: string; icon?: IconName; titleId?: string; level?: 2 | 3 }>();
</script>

<template>
    <div class="mb-4 flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <span
                v-if="icon"
                class="inline-flex size-(--control-sm) shrink-0 items-center justify-center rounded-full bg-surface-raised text-highlight-fg"
            >
                <Icon :name="icon" />
            </span>
            <div class="min-w-0">
                <component :is="`h${level ?? 2}`" :id="titleId" class="truncate font-display text-lead font-medium">{{
                    title
                }}</component>
                <p v-if="subtitle" class="truncate text-label text-fg-muted">{{ subtitle }}</p>
            </div>
        </div>
        <div v-if="$slots.default" class="flex shrink-0 items-center gap-2"><slot /></div>
    </div>
</template>
