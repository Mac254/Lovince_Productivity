<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useId } from 'vue';
import type { IconName } from '../../icons/registry';
import Icon from '../../primitives/Icon/Icon.vue';
import { buttonVariants, controlSquares, interactive, type ButtonSize } from '../Button/variants';
import type { MenuItem } from './types';

/** Menu button pattern: arrow keys, Home/End, Escape and Tab behave as in native menus. */
withDefaults(
    defineProps<{
        items: MenuItem[];
        label: string;
        icon?: IconName;
        size?: ButtonSize;
        triggerVariant?: 'outline' | 'ghost';
    }>(),
    { icon: 'more', size: 'md', triggerVariant: 'outline' },
);

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
const menuId = useId();

function menuItems(): HTMLElement[] {
    return Array.from(root.value?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? []);
}

async function show(focusIndex = 0): Promise<void> {
    open.value = true;
    document.addEventListener('pointerdown', onOutside);
    await nextTick();
    menuItems().at(focusIndex)?.focus();
}

function hide(restoreFocus = true): void {
    open.value = false;
    document.removeEventListener('pointerdown', onOutside);
    if (restoreFocus) trigger.value?.focus();
}

function onOutside(event: PointerEvent): void {
    if (!root.value?.contains(event.target as Node)) hide(false);
}

function toggle(): void {
    if (open.value) hide();
    else void show();
}

function onTriggerKey(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        void show(event.key === 'ArrowDown' ? 0 : -1);
    }
}

function onMenuKey(event: KeyboardEvent): void {
    const items = menuItems();
    const index = items.indexOf(document.activeElement as HTMLElement);
    const focusAt = (i: number) => items.at((i + items.length) % items.length)?.focus();

    if (event.key === 'ArrowDown') focusAt(index + 1);
    else if (event.key === 'ArrowUp') focusAt(index - 1);
    else if (event.key === 'Home') focusAt(0);
    else if (event.key === 'End') focusAt(-1);
    else if (event.key === 'Escape') hide();
    else if (event.key === 'Tab') hide(false);
    else return;
    if (event.key !== 'Tab') event.preventDefault();
}

function select(item: MenuItem): void {
    hide();
    item.onSelect();
}

onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside));
</script>

<template>
    <div ref="root" class="relative">
        <button
            ref="trigger"
            type="button"
            :aria-label="label"
            :title="label"
            aria-haspopup="menu"
            :aria-expanded="open"
            :aria-controls="open ? menuId : undefined"
            :class="[interactive, controlSquares[size], buttonVariants[triggerVariant], open && 'bg-surface']"
            @click="toggle"
            @keydown="onTriggerKey"
        >
            <slot name="trigger">
                <Icon :name="icon" />
            </slot>
        </button>
        <div
            v-if="open"
            :id="menuId"
            role="menu"
            :aria-label="label"
            class="absolute top-full right-0 z-(--z-dropdown) mt-2 min-w-48 rounded-md border border-border bg-surface-raised p-1 shadow-floating"
            @keydown="onMenuKey"
        >
            <slot name="header" />
            <button
                v-for="item in items"
                :key="item.label"
                type="button"
                role="menuitem"
                tabindex="-1"
                :class="[
                    'flex w-full items-center gap-3 rounded-sm px-3 py-2 text-left text-label font-semibold',
                    'transition-colors duration-(--duration-instant) hover:bg-border focus-visible:bg-border focus-visible:outline-none',
                    item.destructive ? 'text-danger-fg' : 'text-fg',
                ]"
                @click="select(item)"
            >
                <Icon v-if="item.icon" :name="item.icon" />
                {{ item.label }}
            </button>
        </div>
    </div>
</template>
