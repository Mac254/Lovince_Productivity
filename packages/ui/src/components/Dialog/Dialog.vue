<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import Heading from '../../primitives/Heading/Heading.vue';
import IconButton from '../IconButton/IconButton.vue';

/** Modal dialog on the native `<dialog>`: focus trap, Escape and inert background for free. */
withDefaults(defineProps<{ title: string; description?: string; size?: 'sm' | 'md' }>(), { size: 'md' });
const open = defineModel<boolean>('open', { required: true });

const dialog = ref<HTMLDialogElement | null>(null);
const titleId = useId();
const descriptionId = useId();
let returnFocus: HTMLElement | null = null;

function sync(value: boolean): void {
    const el = dialog.value;
    if (!el) return;
    if (value && !el.open) {
        returnFocus = document.activeElement as HTMLElement | null;
        el.showModal();
    } else if (!value && el.open) {
        el.close();
    }
}

function onClose(): void {
    open.value = false;
    returnFocus?.focus?.();
}

function onBackdrop(event: MouseEvent): void {
    if (event.target === dialog.value) open.value = false;
}

watch(open, sync);
onMounted(() => sync(open.value));
onBeforeUnmount(() => dialog.value?.open && dialog.value.close());
</script>

<template>
    <dialog
        ref="dialog"
        :aria-labelledby="titleId"
        :aria-describedby="description ? descriptionId : undefined"
        :class="[
            'm-auto max-h-(--dialog-max-height) w-(--dialog-width) overflow-y-auto rounded-lg border border-border bg-surface-overlay p-0 text-fg shadow-overlay',
            'backdrop:bg-scrim',
            size === 'sm' ? 'max-w-form' : 'max-w-dialog',
        ]"
        @close="onClose"
        @click="onBackdrop"
    >
        <div class="p-6 sm:p-8">
            <header class="mb-6 flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <Heading :id="titleId" :level="2" role="heading">{{ title }}</Heading>
                    <p v-if="description" :id="descriptionId" class="text-label text-fg-muted">{{ description }}</p>
                </div>
                <IconButton icon="close" label="Close dialog" size="sm" @click="open = false" />
            </header>
            <slot />
            <footer v-if="$slots.footer" class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <slot name="footer" />
            </footer>
        </div>
    </dialog>
</template>
