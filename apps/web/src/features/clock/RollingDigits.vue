<script setup lang="ts">
import { computed } from 'vue';

/**
 * Renders text one character per slot; a slot animates only when its character changes,
 * rolling the old value up and out while the new one rises in. Separators (":") breathe.
 * Screen readers get the plain text once (`label` overrides it); the animated glyphs are hidden.
 */
const props = defineProps<{ text: string; label?: string }>();

const chars = computed(() => [...props.text].map((char, index) => ({ char, index, separator: /[:.\s]/.test(char) })));
</script>

<template>
    <span class="rolling">
        <span class="sr-only">{{ label ?? text }}</span>
        <span
            v-for="slot in chars"
            :key="slot.index"
            :class="['slot', slot.separator && 'separator']"
            aria-hidden="true"
        >
            <Transition name="roll">
                <span :key="slot.char" class="glyph">{{ slot.char }}</span>
            </Transition>
        </span>
    </span>
</template>

<style scoped>
.rolling {
    display: inline-flex;
    font-variant-numeric: tabular-nums;
}

.slot {
    position: relative;
    display: inline-block;
    /* Clip only the vertical roll; glyphs may overhang sideways with tight tracking. */
    overflow-x: visible;
    overflow-y: clip;
    /* Room for the glyph to travel without clipping ascenders. */
    padding-block: 0.06em;
    margin-block: -0.06em;
}

.glyph {
    display: inline-block;
}

.separator .glyph {
    animation: breathe 2s var(--ease-in-out) infinite;
}

.roll-enter-active,
.roll-leave-active {
    transition:
        transform var(--duration-slow) var(--ease-out),
        opacity var(--duration-slow) var(--ease-out);
}

.roll-leave-active {
    position: absolute;
    inset-inline: 0;
    top: 0.06em;
}

.roll-enter-from {
    transform: translateY(55%);
    opacity: 0;
}

.roll-leave-to {
    transform: translateY(-55%);
    opacity: 0;
}

@keyframes breathe {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.35;
    }
}
</style>
