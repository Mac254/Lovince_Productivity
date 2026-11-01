<script setup lang="ts">
import { LvToastClose, LvToastRoot, LvToastTitle, LvToastViewport } from '@lovince/ui-vue';
import Icon from '../../primitives/Icon/Icon.vue';
import type { IconName } from '../../icons/registry';
import type { Toast } from './useToast';

const icons: Record<Toast['tone'], IconName> = {
    default: 'info',
    success: 'check-circle',
    warning: 'warning',
    error: 'warning',
    loading: 'spinner',
};

/** Viewport slot props are untyped, so each toast is read through its type here. */
const iconFor = (toast: Toast): IconName => icons[toast.tone];
const isError = (toast: Toast): boolean => toast.tone === 'error';
const isLoading = (toast: Toast): boolean => toast.tone === 'loading';
</script>

<template>
    <LvToastViewport
        v-slot="{ toasts }"
        class="pointer-events-none fixed inset-x-4 bottom-24 z-(--z-toast) flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:bottom-6 sm:items-end"
    >
        <LvToastRoot
            v-for="item in toasts"
            :key="item.id"
            :toast="item"
            :class="[
                'toast pointer-events-auto flex w-full max-w-form items-center gap-3 rounded-md py-2 pr-2 pl-4 text-label font-semibold shadow-floating',
                isError(item) ? 'bg-highlight text-highlight-ink' : 'bg-surface-inverse text-fg-inverse',
            ]"
        >
            <Icon :name="iconFor(item)" :class="isLoading(item) && 'animate-spin'" />
            <LvToastTitle class="flex-1" />
            <LvToastClose
                class="inline-flex size-(--control-sm) items-center justify-center rounded-full transition-colors duration-(--duration-instant) hover:bg-fg-inverse hover:text-surface-inverse"
            >
                <Icon name="close" />
            </LvToastClose>
        </LvToastRoot>
    </LvToastViewport>
</template>

<style scoped>
.toast[data-state='open'] {
    animation: toast-in var(--duration-normal) var(--ease-out);
}

.toast[data-state='closed'] {
    animation: toast-out var(--duration-fast) var(--ease-in) forwards;
}

@keyframes toast-in {
    from {
        opacity: 0;
        translate: 0 calc(var(--spacing) * 2);
    }
}

@keyframes toast-out {
    to {
        opacity: 0;
    }
}
</style>
