<script setup lang="ts">
import { LvCardDescription, LvCardHeader, LvCardTitle } from '@lovince/ui-vue';
import type { IconName } from '../../icons/registry';
import Icon from '../../primitives/Icon/Icon.vue';

/** Title row for a card: optional icon chip, title, subtitle, and actions on the right. */
defineProps<{ title: string; subtitle?: string; icon?: IconName; titleId?: string; level?: 2 | 3 }>();
</script>

<template>
    <LvCardHeader class="mb-4 flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <span
                v-if="icon"
                class="inline-flex size-(--control-sm) shrink-0 items-center justify-center rounded-full bg-surface-raised text-highlight-fg"
            >
                <Icon :name="icon" />
            </span>
            <div class="min-w-0">
                <LvCardTitle :id="titleId" :as="`h${level ?? 2}`" class="truncate font-display text-lead font-medium">{{
                    title
                }}</LvCardTitle>
                <LvCardDescription v-if="subtitle" class="truncate text-label text-fg-muted">{{
                    subtitle
                }}</LvCardDescription>
            </div>
        </div>
        <div v-if="$slots.default" class="flex shrink-0 items-center gap-2"><slot /></div>
    </LvCardHeader>
</template>
