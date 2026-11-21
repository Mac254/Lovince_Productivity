<script setup lang="ts">
import {
    LvSheetClose,
    LvSheetContent,
    LvSheetDescription,
    LvSheetOverlay,
    LvSheetPortal,
    LvSheetRoot,
    LvSheetTitle,
} from '@lovince/ui-vue';
import { ref, useTemplateRef, watch } from 'vue';
import { provideLayer } from '../../composables/useLayer';
import IconButton from '../IconButton/IconButton.vue';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        tone?: 'surface' | 'chrome';
        side?: 'left' | 'right';
        size?: 'form' | 'nav';
    }>(),
    { tone: 'surface', side: 'right', size: 'form' },
);
const open = defineModel<boolean>('open', { required: true });
provideLayer(useTemplateRef<HTMLElement>('layer'));

const swiped = ref(false);
let start: number | null = null;
watch(open, (value) => {
    if (value) swiped.value = false;
});
const onPointerDown = (event: PointerEvent) => {
    start = event.pointerType === 'touch' ? event.clientX : null;
};
const onPointerMove = (event: PointerEvent) => {
    if (start !== null && Math.abs(event.clientX - start) > 8) swiped.value = true;
};
const onPointerEnd = () => {
    start = null;
};

const sides = {
    right: 'right-3 left-3 sm:left-auto',
    left: 'left-3 right-3 sm:right-auto',
};
</script>

<template>
    <LvSheetRoot v-model:open="open" :side="props.side" variant="floating">
        <LvSheetPortal>
            <LvSheetOverlay class="sheet-overlay fixed inset-0 z-(--z-overlay) bg-scrim" />
            <LvSheetContent
                :data-swiped="swiped ? '' : undefined"
                :class="[
                    'sheet fixed inset-y-3 z-(--z-modal) flex flex-col overflow-hidden rounded-lg border shadow-overlay focus-visible:outline-none sm:w-full',
                    sides[props.side],
                    props.size === 'nav' ? 'max-w-(--sidebar-width)' : 'max-w-form',
                    tone === 'chrome'
                        ? 'border-chrome-border bg-chrome text-chrome-fg'
                        : 'border-border bg-surface-overlay text-fg',
                ]"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerEnd"
                @pointercancel="onPointerEnd"
            >
                <header
                    :class="[
                        'flex items-start justify-between gap-4 border-b p-6',
                        tone === 'chrome' ? 'border-chrome-border' : 'border-border',
                    ]"
                >
                    <div class="flex flex-col gap-1">
                        <LvSheetTitle class="font-display text-heading font-medium tracking-tight text-balance">
                            {{ title }}
                        </LvSheetTitle>
                        <LvSheetDescription
                            v-if="description"
                            :class="['text-label', tone === 'chrome' ? 'text-chrome-fg-muted' : 'text-fg-muted']"
                        >
                            {{ description }}
                        </LvSheetDescription>
                    </div>
                    <LvSheetClose as-child>
                        <IconButton
                            icon="close"
                            :label="`Close ${title.toLowerCase()}`"
                            :variant="tone === 'chrome' ? 'secondary' : 'ghost'"
                            size="sm"
                        />
                    </LvSheetClose>
                </header>
                <div class="flex flex-1 flex-col gap-8 overflow-y-auto p-6">
                    <slot />
                </div>
                <div ref="layer" />
            </LvSheetContent>
        </LvSheetPortal>
    </LvSheetRoot>
</template>

<style scoped>
.sheet[data-state='open']:not([data-swiping], [data-settling]) {
    animation: sheet-in-right var(--duration-normal) var(--ease-out);
}

.sheet[data-state='closed'] {
    animation: sheet-in-right var(--duration-fast) var(--ease-in) reverse forwards;
}

.sheet[data-side='left'][data-state='open']:not([data-swiping], [data-settling]) {
    animation-name: sheet-in-left;
}

.sheet[data-side='left'][data-state='closed'] {
    animation-name: sheet-in-left;
}

.sheet[data-state='closed'][data-swiped] {
    animation: none;
}

.sheet-overlay[data-state='open'] {
    animation: fade-in var(--duration-normal) var(--ease-out);
}

.sheet-overlay[data-state='closed'] {
    animation: fade-out var(--duration-fast) var(--ease-in) forwards;
}

@keyframes sheet-in-right {
    from {
        transform: translateX(110%);
    }
}

@keyframes sheet-in-left {
    from {
        transform: translateX(-110%);
    }
}

@keyframes fade-in {
    from {
        opacity: 0;
    }
}

@keyframes fade-out {
    to {
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .sheet[data-state],
    .sheet-overlay[data-state] {
        animation: none;
    }
}
</style>
