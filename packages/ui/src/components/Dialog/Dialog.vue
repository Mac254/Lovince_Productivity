<script setup lang="ts">
import {
    LvDialogClose,
    LvDialogContent,
    LvDialogDescription,
    LvDialogOverlay,
    LvDialogPortal,
    LvDialogRoot,
    LvDialogTitle,
} from '@lovince/ui-vue';
import { ref, useTemplateRef } from 'vue';
import { provideLayer } from '../../composables/useLayer';
import IconButton from '../IconButton/IconButton.vue';
import ScrollArea from '../ScrollArea/ScrollArea.vue';

/**
 * Modal dialog on Lovince: focus trap, Escape, outside click, scroll lock and focus return.
 * `alert` makes it an alertdialog; `initialFocus` names the element id to focus on open.
 * `busy` keeps it open (no Escape, outside click or close button) while a save is in flight.
 */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        size?: 'sm' | 'md' | 'lg';
        alert?: boolean;
        initialFocus?: string;
        busy?: boolean;
    }>(),
    { size: 'md' },
);
const open = defineModel<boolean>('open', { required: true });
provideLayer(useTemplateRef<HTMLElement>('layer'));
const edges = ref({ top: false, bottom: false });
</script>

<template>
    <LvDialogRoot v-model:open="open" :role="alert ? 'alertdialog' : 'dialog'" :initial-focus="initialFocus">
        <LvDialogPortal>
            <LvDialogOverlay class="dialog-overlay fixed inset-0 z-(--z-overlay) bg-scrim" />
            <LvDialogContent
                :close-on-escape="!busy"
                :close-on-outside="!busy"
                :class="[
                    'dialog fixed inset-0 z-(--z-modal) m-auto flex h-fit max-h-(--dialog-max-height) w-(--dialog-width) flex-col overflow-hidden rounded-lg border border-border bg-surface-overlay text-fg shadow-overlay focus-visible:outline-none',
                    { sm: 'max-w-form', md: 'max-w-modal', lg: 'max-w-dialog-wide' }[size],
                ]"
            >
                <header
                    :class="[
                        'flex shrink-0 items-start justify-between gap-4 border-b px-6 pt-6 pb-4 transition-colors duration-(--duration-fast) sm:px-8 sm:pt-8',
                        edges.top ? 'border-border' : 'border-transparent',
                    ]"
                >
                    <div class="flex flex-col gap-1">
                        <LvDialogTitle class="font-display text-heading font-medium tracking-tight text-balance">
                            {{ title }}
                        </LvDialogTitle>
                        <LvDialogDescription v-if="description" class="text-label text-fg-muted">
                            {{ description }}
                        </LvDialogDescription>
                    </div>
                    <LvDialogClose as-child>
                        <IconButton icon="close" label="Close dialog" size="sm" :disabled="busy" />
                    </LvDialogClose>
                </header>
                <ScrollArea
                    :label="title"
                    :viewport-class="['px-6 pt-4 sm:px-8', $slots.footer ? 'pb-6' : 'pb-6 sm:pb-8']"
                    @edges="edges = $event"
                >
                    <slot />
                </ScrollArea>
                <footer
                    v-if="$slots.footer"
                    :class="[
                        'flex shrink-0 flex-col-reverse gap-3 border-t px-6 py-4 transition-colors duration-(--duration-fast) sm:flex-row sm:justify-end sm:px-8',
                        edges.bottom ? 'border-border' : 'border-transparent',
                    ]"
                >
                    <slot name="footer" />
                </footer>
                <div ref="layer" />
            </LvDialogContent>
        </LvDialogPortal>
    </LvDialogRoot>
</template>

<style scoped>
.dialog[data-state='open'] {
    animation: dialog-in var(--duration-normal) var(--ease-out);
}

.dialog[data-state='closed'] {
    animation: fade-out var(--duration-fast) var(--ease-in) forwards;
}

.dialog-overlay[data-state='open'] {
    animation: fade-in var(--duration-normal) var(--ease-out);
}

.dialog-overlay[data-state='closed'] {
    animation: fade-out var(--duration-fast) var(--ease-in) forwards;
}

@keyframes dialog-in {
    from {
        opacity: 0;
        translate: 0 calc(var(--spacing) * 2);
        scale: 0.98;
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
</style>
