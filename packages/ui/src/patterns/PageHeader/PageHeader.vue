<script setup lang="ts">
/** Page title, optional description, and the page's primary actions on the right. */
defineProps<{ title: string; description?: string; titleId?: string }>();
</script>

<template>
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 :id="titleId" class="font-display text-heading font-semibold tracking-tight sm:text-display-sm">
                <slot name="title">{{ title }}</slot>
            </h1>
            <p v-if="description || $slots.description" class="mt-1 text-body text-fg-muted">
                <slot name="description">{{ description }}</slot>
            </p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
    </header>
</template>
