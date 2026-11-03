<script setup lang="ts">
import {
    LvScrollAreaRoot,
    LvScrollAreaScrollbar,
    LvScrollAreaThumb,
    LvScrollAreaViewport,
    useScrollEdges,
} from '@lovince/ui-vue';
import { useTemplateRef, watch } from 'vue';

/**
 * Lovince scroll area with the app's thin thumb. Native scrolling, keyboard-operable thumb.
 * Emits `edges` (content hidden above / below) so a parent can show dividers only while needed.
 */
defineProps<{ label: string; viewportClass?: unknown }>();
const emit = defineEmits<{ edges: [edges: { top: boolean; bottom: boolean }] }>();

const { edges } = useScrollEdges(useTemplateRef('viewport'));
watch(edges, (value) => emit('edges', { top: value.top, bottom: value.bottom }), { immediate: true });
</script>

<template>
    <LvScrollAreaRoot v-slot="{ vertical }" :label="label" class="flex min-h-0 flex-1 flex-col">
        <LvScrollAreaViewport
            ref="viewport"
            :style="{ height: 'auto' }"
            :class="['min-h-0 flex-1 overscroll-contain', viewportClass]"
        >
            <slot />
        </LvScrollAreaViewport>
        <LvScrollAreaScrollbar v-if="vertical" class="track my-2 mr-1">
            <LvScrollAreaThumb class="thumb" />
        </LvScrollAreaScrollbar>
    </LvScrollAreaRoot>
</template>

<style scoped>
.track {
    --scroll-area-size: calc(var(--spacing) * 1.5);
}

.thumb {
    background: var(--border-control);
    transition: background var(--duration-fast);
}

.track:hover .thumb,
.thumb[data-dragging] {
    background: var(--fg-muted);
}

.thumb:focus-visible {
    outline: 2px solid var(--focus-ring);
    outline-offset: 2px;
}
</style>
