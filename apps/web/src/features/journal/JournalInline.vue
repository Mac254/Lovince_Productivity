<script setup lang="ts">
import type { Inline } from '@/lib/markdownLite';

defineProps<{ parts: Inline[] }>();
</script>

<template>
    <template v-for="(part, index) in parts" :key="`${index}-${part.kind}`">
        <code v-if="part.kind === 'code'" class="inline-code">{{ part.text }}</code>
        <strong v-else-if="part.kind === 'strong'" class="font-semibold">{{ part.text }}</strong>
        <em v-else-if="part.kind === 'em'" class="italic">{{ part.text }}</em>
        <mark v-else-if="part.kind === 'mark'" class="marker text-fg">{{ part.text }}</mark>
        <s v-else-if="part.kind === 'strike'" class="text-fg-muted">{{ part.text }}</s>
        <template v-else>{{ part.text }}</template>
    </template>
</template>

<style scoped>
.inline-code {
    border-radius: var(--radius-sm);
    background: var(--surface-raised);
    padding: 0.1em 0.35em;
    font-family: var(--font-mono);
    font-size: 0.875em;
}

.marker {
    border-radius: var(--radius-sm);
    background: color-mix(in oklab, var(--highlight) 32%, transparent);
    padding: 0 0.15em;
    box-decoration-break: clone;
}
</style>
