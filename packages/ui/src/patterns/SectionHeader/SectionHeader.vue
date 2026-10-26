<script setup lang="ts">
/** Section title with an optional count, plus filters or actions on the right. */
defineProps<{ title: string; count?: number | null; titleId?: string }>();
</script>

<template>
    <div class="flex flex-col items-start gap-3 lg:flex-row lg:items-center lg:justify-between">
        <h2 :id="titleId" class="inline-flex items-center gap-2 font-display text-title font-semibold tracking-tight">
            {{ title }}
            <span
                v-if="count !== undefined && count !== null"
                class="inline-flex min-w-(--control-sm) justify-center rounded-full bg-highlight px-2 py-1 text-caption font-bold text-highlight-ink tabular"
            >
                {{ count }}
            </span>
        </h2>
        <slot />
    </div>
</template>
