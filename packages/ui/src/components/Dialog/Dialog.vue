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
import IconButton from '../IconButton/IconButton.vue';

/**
 * Modal dialog on Lovince: focus trap, Escape, outside click, scroll lock and focus return.
 * `alert` makes it an alertdialog; `initialFocus` names the element id to focus on open.
 */
withDefaults(
    defineProps<{ title: string; description?: string; size?: 'sm' | 'md'; alert?: boolean; initialFocus?: string }>(),
    { size: 'md' },
);
const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <LvDialogRoot v-model:open="open" :role="alert ? 'alertdialog' : 'dialog'" :initial-focus="initialFocus">
        <LvDialogPortal>
            <LvDialogOverlay class="dialog-overlay fixed inset-0 z-(--z-overlay) bg-scrim" />
            <LvDialogContent
                :class="[
                    'dialog fixed inset-0 z-(--z-modal) m-auto h-fit max-h-(--dialog-max-height) w-(--dialog-width) overflow-y-auto rounded-lg border border-border bg-surface-overlay text-fg shadow-overlay focus-visible:outline-none',
                    size === 'sm' ? 'max-w-form' : 'max-w-dialog',
                ]"
            >
                <div class="p-6 sm:p-8">
                    <header class="mb-6 flex items-start justify-between gap-4">
                        <div class="flex flex-col gap-1">
                            <LvDialogTitle class="font-display text-heading font-medium tracking-tight text-balance">
                                {{ title }}
                            </LvDialogTitle>
                            <LvDialogDescription v-if="description" class="text-label text-fg-muted">
                                {{ description }}
                            </LvDialogDescription>
                        </div>
                        <LvDialogClose as-child>
                            <IconButton icon="close" label="Close dialog" size="sm" />
                        </LvDialogClose>
                    </header>
                    <slot />
                    <footer v-if="$slots.footer" class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <slot name="footer" />
                    </footer>
                </div>
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
