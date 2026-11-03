<script setup lang="ts">
const props = defineProps<{ text: string }>();

const chars = computed(() => [...props.text].map((char, index) => ({ char, index, separator: /[:.\s]/.test(char) })));
</script>

<template>
    <span class="flip" aria-hidden="true">
        <span v-for="slot in chars" :key="slot.index" :class="slot.separator ? 'flip-separator' : 'flip-card'">
            <template v-if="slot.separator">{{ slot.char }}</template>
            <Transition v-else name="flip">
                <span :key="slot.char" class="flip-glyph">{{ slot.char }}</span>
            </Transition>
        </span>
    </span>
</template>

<style scoped>
.flip {
    display: inline-flex;
    align-items: center;
    gap: 0.08em;
    font-variant-numeric: tabular-nums;
    perspective: 6em;
}

.flip-card {
    position: relative;
    display: inline-grid;
    place-items: center;
    width: 0.78em;
    height: 1.2em;
    overflow: clip;
    border-radius: var(--radius-md);
    background: var(--chrome);
    box-shadow: var(--shadow-floating);
    color: var(--chrome-fg);
}

.flip-card::after {
    content: '';
    position: absolute;
    inset-inline: 0;
    top: 50%;
    height: 1px;
    background: color-mix(in oklab, var(--chrome-fg) 18%, transparent);
}

.flip-glyph {
    grid-area: 1 / 1;
    line-height: 1;
    backface-visibility: hidden;
}

.flip-separator {
    padding-inline: 0.02em;
    line-height: 1;
    opacity: 0.7;
}

.flip-enter-active,
.flip-leave-active {
    transition:
        transform var(--duration-slow) var(--ease-out),
        opacity var(--duration-slow) var(--ease-out);
    transform-origin: 50% 50%;
}

.flip-enter-from {
    opacity: 0;
    transform: rotateX(90deg);
}

.flip-leave-to {
    opacity: 0;
    transform: rotateX(-90deg);
}

@media (prefers-reduced-motion: reduce) {
    .flip-enter-active,
    .flip-leave-active {
        transition: none;
    }
}
</style>
