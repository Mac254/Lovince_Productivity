<script setup lang="ts">
import {
    LvDropdownMenuContent,
    LvDropdownMenuItem,
    LvDropdownMenuPortal,
    LvDropdownMenuRoot,
    LvDropdownMenuTrigger,
} from '@lovince/ui-vue';
import { ref } from 'vue';
import { useLayer } from '../../composables/useLayer';
import type { IconName } from '../../icons/registry';
import Icon from '../../primitives/Icon/Icon.vue';
import Tooltip from '../Tooltip/Tooltip.vue';
import { buttonVariants, controlSquares, interactive, type ButtonSize } from '../Button/variants';
import type { MenuItem } from './types';

/**
 * Menu button on Lovince UI's menu machine: arrow keys, Home/End, typeahead, Escape, Tab and
 * outside clicks behave as in native menus, and the menu flips to stay on screen.
 */
withDefaults(
    defineProps<{
        items: MenuItem[];
        label: string;
        icon?: IconName;
        size?: ButtonSize;
        triggerVariant?: 'outline' | 'ghost';
    }>(),
    { icon: 'more', size: 'md', triggerVariant: 'ghost' },
);

const open = ref(false);
const layer = useLayer();
</script>

<template>
    <LvDropdownMenuRoot v-model:open="open">
        <Tooltip :label="label" :disabled="open">
            <LvDropdownMenuTrigger
                :aria-label="label"
                :class="[interactive, controlSquares[size], buttonVariants[triggerVariant], open && 'bg-border text-fg']"
            >
                <slot name="trigger">
                    <Icon :name="icon" />
                </slot>
            </LvDropdownMenuTrigger>
        </Tooltip>
        <LvDropdownMenuPortal :to="layer">
            <LvDropdownMenuContent
                align="end"
                :side-offset="8"
                class="menu z-(--z-popover) min-w-48 rounded-md border border-border bg-surface-raised p-1 text-fg shadow-floating focus-visible:outline-none"
            >
                <slot name="header" />
                <LvDropdownMenuItem
                    v-for="item in items"
                    :key="item.label"
                    :class="[
                        'flex w-full cursor-pointer items-center gap-3 rounded-sm px-3 py-2 text-left text-label font-semibold outline-none',
                        'transition-colors duration-(--duration-instant) data-highlighted:bg-border',
                        item.destructive ? 'text-danger-fg' : 'text-fg',
                    ]"
                    @select="item.onSelect()"
                >
                    <Icon v-if="item.icon" :name="item.icon" />
                    {{ item.label }}
                </LvDropdownMenuItem>
            </LvDropdownMenuContent>
        </LvDropdownMenuPortal>
    </LvDropdownMenuRoot>
</template>

<style scoped>
/* Lovince positions the menu with `transform`, so the entrance uses `translate`/`scale`, which compose with it. */
.menu {
    --menu-nudge: calc(var(--spacing) * -1);
}

.menu[data-side='top'] {
    --menu-nudge: var(--spacing);
}

.menu[data-state='open'] {
    animation: menu-in var(--duration-fast) var(--ease-out);
}

.menu[data-state='closed'] {
    animation: menu-out var(--duration-instant) var(--ease-in) forwards;
}

@keyframes menu-in {
    from {
        opacity: 0;
        translate: 0 var(--menu-nudge);
        scale: 0.98;
    }
}

@keyframes menu-out {
    to {
        opacity: 0;
    }
}
</style>
