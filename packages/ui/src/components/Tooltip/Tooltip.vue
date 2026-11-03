<script setup lang="ts">
import { LvTooltipContent, LvTooltipPortal, LvTooltipRoot, LvTooltipTrigger } from '@lovince/ui-vue';
import { useLayer } from '../../composables/useLayer';

/** A short hint on hover and keyboard focus. The trigger is the slotted element itself. */
withDefaults(
    defineProps<{
        label: string;
        shortcut?: string;
        side?: 'top' | 'right' | 'bottom' | 'left';
        disabled?: boolean;
    }>(),
    { side: 'top' },
);

const layer = useLayer();
</script>

<template>
    <LvTooltipRoot :disabled="disabled">
        <LvTooltipTrigger as-child>
            <slot />
        </LvTooltipTrigger>
        <LvTooltipPortal :to="layer">
            <LvTooltipContent
                :side="side"
                :side-offset="6"
                :collision-padding="8"
                class="tooltip pointer-events-none z-(--z-popover) flex items-center gap-2 rounded-sm bg-surface-inverse px-2 py-1 text-caption font-semibold text-fg-inverse shadow-floating"
            >
                {{ label }}
                <kbd v-if="shortcut" class="font-mono text-caption font-normal opacity-70">{{ shortcut }}</kbd>
            </LvTooltipContent>
        </LvTooltipPortal>
    </LvTooltipRoot>
</template>

<style scoped>
.tooltip[data-state='open'] {
    animation: tooltip-in var(--duration-fast) var(--ease-out);
}

@keyframes tooltip-in {
    from {
        opacity: 0;
        scale: 0.96;
    }
}
</style>
