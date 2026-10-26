<script setup lang="ts">
import Icon from '../../primitives/Icon/Icon.vue';
import type { IconName } from '../../icons/registry';
import { useToast, type Toast } from './useToast';

const { toasts, dismiss } = useToast();
const icons: Record<Toast['tone'], IconName> = { success: 'check-circle', error: 'warning', info: 'info' };
</script>

<template>
    <div
        aria-live="polite"
        class="pointer-events-none fixed inset-x-4 bottom-24 z-(--z-toast) flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:bottom-6 sm:items-end"
    >
        <TransitionGroup
            enter-from-class="translate-y-2 opacity-0"
            leave-to-class="opacity-0"
            enter-active-class="transition duration-(--duration-normal) ease-out"
            leave-active-class="transition duration-(--duration-fast) ease-in"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :role="toast.tone === 'error' ? 'alert' : 'status'"
                :class="[
                    'pointer-events-auto flex w-full max-w-form items-center gap-3 rounded-md py-2 pr-2 pl-4 text-label font-semibold shadow-floating',
                    toast.tone === 'error' ? 'bg-highlight text-highlight-ink' : 'bg-surface-inverse text-fg-inverse',
                ]"
            >
                <Icon :name="icons[toast.tone]" />
                <span class="flex-1">{{ toast.message }}</span>
                <button
                    type="button"
                    class="inline-flex size-(--control-sm) items-center justify-center rounded-full transition-colors duration-(--duration-instant) hover:bg-fg-inverse hover:text-surface-inverse"
                    aria-label="Dismiss notification"
                    @click="dismiss(toast.id)"
                >
                    <Icon name="close" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
