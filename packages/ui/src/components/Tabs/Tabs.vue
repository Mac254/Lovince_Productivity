<script setup lang="ts" generic="T extends string">
import { LvTabsContent, LvTabsList, LvTabsRoot, LvTabsTrigger } from '@lovince/ui-vue';
import Icon from '../../primitives/Icon/Icon.vue';
import type { TabItem } from './types';

defineProps<{ label: string; tabs: TabItem<T>[] }>();
const model = defineModel<T>({ required: true });
defineSlots<{ [K in T]?: () => unknown }>();

function select(value: string): void {
    model.value = value as T;
}
</script>

<template>
    <LvTabsRoot :model-value="model" class="flex flex-col gap-6" @update:model-value="select">
        <LvTabsList :aria-label="label" class="flex gap-1 overflow-x-auto border-b border-border">
            <LvTabsTrigger
                v-for="tab in tabs"
                :key="tab.value"
                :value="tab.value"
                :class="[
                    'inline-flex h-(--control-md) shrink-0 items-center gap-2 border-b-2 border-transparent px-3',
                    'text-label font-semibold text-fg-muted transition-colors duration-(--duration-instant) hover:text-fg',
                    'focus-visible:outline-2 focus-visible:outline-focus-ring',
                    'aria-selected:border-highlight aria-selected:text-fg',
                ]"
            >
                <Icon v-if="tab.icon" :name="tab.icon" size="sm" />
                {{ tab.label }}
                <span
                    v-if="tab.count"
                    class="inline-flex min-w-(--control-sm) justify-center rounded-full bg-surface-raised px-2 text-caption font-bold tabular"
                >
                    {{ tab.count }}
                </span>
            </LvTabsTrigger>
        </LvTabsList>
        <LvTabsContent
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
            class="flex flex-col gap-6 focus-visible:outline-2 focus-visible:outline-focus-ring"
        >
            <slot :name="tab.value" />
        </LvTabsContent>
    </LvTabsRoot>
</template>
