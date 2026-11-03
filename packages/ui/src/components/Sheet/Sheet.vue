<script setup lang="ts">
import {
    LvDialogClose,
    LvDialogContent,
    LvDialogDescription,
    LvDialogPortal,
    LvDialogRoot,
    LvDialogTitle,
} from '@lovince/ui-vue';
import { useTemplateRef } from 'vue';
import { provideLayer } from '../../composables/useLayer';
import IconButton from '../IconButton/IconButton.vue';

withDefaults(defineProps<{ title: string; description?: string; tone?: 'surface' | 'chrome' }>(), {
    tone: 'surface',
});
const open = defineModel<boolean>('open', { required: true });
provideLayer(useTemplateRef<HTMLElement>('layer'));
</script>

<template>
    <LvDialogRoot v-model:open="open">
        <LvDialogPortal>
            <LvDialogContent
                :class="[
                    'sheet fixed inset-y-0 right-0 z-(--z-modal) flex w-full max-w-form flex-col border-l shadow-overlay focus-visible:outline-none',
                    tone === 'chrome'
                        ? 'border-chrome-border bg-chrome text-chrome-fg'
                        : 'border-border bg-surface-overlay text-fg',
                ]"
            >
                <header
                    :class="[
                        'flex items-start justify-between gap-4 border-b p-6',
                        tone === 'chrome' ? 'border-chrome-border' : 'border-border',
                    ]"
                >
                    <div class="flex flex-col gap-1">
                        <LvDialogTitle class="font-display text-heading font-medium tracking-tight text-balance">
                            {{ title }}
                        </LvDialogTitle>
                        <LvDialogDescription
                            v-if="description"
                            :class="['text-label', tone === 'chrome' ? 'text-chrome-fg-muted' : 'text-fg-muted']"
                        >
                            {{ description }}
                        </LvDialogDescription>
                    </div>
                    <LvDialogClose as-child>
                        <IconButton
                            icon="close"
                            :label="`Close ${title.toLowerCase()}`"
                            :variant="tone === 'chrome' ? 'secondary' : 'ghost'"
                            size="sm"
                        />
                    </LvDialogClose>
                </header>
                <div class="flex flex-1 flex-col gap-8 overflow-y-auto p-6">
                    <slot />
                </div>
                <div ref="layer" />
            </LvDialogContent>
        </LvDialogPortal>
    </LvDialogRoot>
</template>

<style scoped>
.sheet[data-state='open'] {
    animation: sheet-in var(--duration-normal) var(--ease-out);
}

.sheet[data-state='closed'] {
    animation: sheet-in var(--duration-fast) var(--ease-in) reverse forwards;
}

@keyframes sheet-in {
    from {
        transform: translateX(100%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .sheet[data-state] {
        animation: none;
    }
}
</style>
