<script setup lang="ts">
withDefaults(defineProps<{ text?: string }>(), { text: 'Focus' });
</script>

<template>
    <span :aria-label="text" class="inline-flex items-center font-display font-semibold tracking-tight uppercase">
        <span aria-hidden="true" class="inline-flex items-center">
            {{ text.slice(0, 1) }}<span class="ring" />{{ text.slice(2) }}
        </span>
    </span>
</template>

<style scoped>
/* The second letter is drawn as the brand ring; sizes are relative to the glyphs. */
.ring {
    display: inline-block;
    width: 0.78em;
    height: 0.78em;
    margin-inline: 0.04em;
    border: 0.17em solid var(--highlight);
    border-radius: 9999px;
    background: var(--canvas);
    box-shadow: 0 0 0 0.08em var(--accent);
}
</style>
