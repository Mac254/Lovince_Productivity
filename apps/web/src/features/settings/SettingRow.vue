<script setup lang="ts">
import { useId } from 'vue';

/** One key–value row: the setting and what it means on the left, its value and actions on the right. */
defineProps<{ label: string; description?: string }>();
const labelId = useId();
const descriptionId = useId();
</script>

<template>
    <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:gap-6">
        <dt class="flex flex-col gap-1 sm:w-2/5 sm:shrink-0">
            <span :id="labelId" class="text-label font-semibold text-fg">{{ label }}</span>
            <span v-if="description" :id="descriptionId" class="text-caption text-fg-muted">{{ description }}</span>
        </dt>
        <dd class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-3 sm:flex-nowrap">
            <slot :label-id="labelId" :description-id="description ? descriptionId : undefined" />
            <div v-if="$slots.action" class="flex shrink-0 items-center gap-2 sm:ml-auto">
                <slot name="action" />
            </div>
        </dd>
    </div>
</template>
