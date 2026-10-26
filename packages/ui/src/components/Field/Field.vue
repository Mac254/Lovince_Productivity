<script setup lang="ts">
/**
 * Label, hint and error wrapper for any control. The slot receives `describedBy` and
 * `invalid` so the control can wire `aria-describedby` and `aria-invalid`.
 */
defineProps<{ id: string; label: string; error?: string; hint?: string; optional?: boolean }>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <label :for="id" class="text-label font-semibold text-fg">
            {{ label }}
            <span v-if="optional" class="font-medium text-fg-muted">(optional)</span>
        </label>
        <slot :described-by="error ? `${id}-error` : hint ? `${id}-hint` : undefined" :invalid="!!error" />
        <p v-if="error" :id="`${id}-error`" class="text-label text-danger-fg">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="text-label text-fg-muted">{{ hint }}</p>
    </div>
</template>
