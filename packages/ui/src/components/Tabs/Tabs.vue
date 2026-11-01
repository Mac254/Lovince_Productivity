<script setup lang="ts" generic="T extends string">
import { useLiquid } from '@focus/motion';
import { LvTabsContent, LvTabsList, LvTabsRoot, LvTabsTrigger } from '@lovince/ui-vue';
import { ref } from 'vue';
import Icon from '../../primitives/Icon/Icon.vue';
import type { TabItem } from './types';

defineProps<{ label: string; tabs: TabItem<T>[] }>();
const model = defineModel<T>({ required: true });
defineSlots<{ [K in T]?: () => unknown }>();

const bar = ref<HTMLElement | null>(null);
useLiquid(bar);

function select(value: string): void {
    model.value = value as T;
}
</script>

<template>
    <LvTabsRoot :model-value="model" class="flex flex-col gap-6" @update:model-value="select">
        <div ref="bar" data-liquid class="overflow-x-auto rounded-lg border border-border bg-surface p-1">
            <span data-liquid-indicator aria-hidden="true" class="bg-surface-raised shadow-card" />
            <LvTabsList :aria-label="label" class="flex gap-1">
                <LvTabsTrigger
                    v-for="tab in tabs"
                    :key="tab.value"
                    :value="tab.value"
                    data-liquid-item
                    :class="[
                        'inline-flex h-(--control-md) flex-1 shrink-0 items-center justify-center gap-2 rounded-md px-4',
                        'text-label font-semibold text-fg-muted transition-colors duration-(--duration-fast) hover:text-fg',
                        'focus-visible:outline-2 focus-visible:outline-focus-ring aria-selected:text-fg',
                    ]"
                >
                    <Icon v-if="tab.icon" :name="tab.icon" size="sm" />
                    {{ tab.label }}
                    <span
                        v-if="tab.count"
                        class="inline-flex min-w-(--control-sm) justify-center rounded-full bg-highlight px-2 text-caption font-bold text-highlight-ink tabular"
                    >
                        {{ tab.count }}
                    </span>
                </LvTabsTrigger>
            </LvTabsList>
        </div>
        <LvTabsContent
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
            class="tab-panel flex flex-col gap-6 focus-visible:outline-2 focus-visible:outline-focus-ring"
        >
            <slot :name="tab.value" />
        </LvTabsContent>
    </LvTabsRoot>
</template>

<style scoped>
.tab-panel:not([hidden]) {
    animation: panel-in var(--duration-normal) var(--ease-out);
}

@keyframes panel-in {
    from {
        opacity: 0;
        translate: 0 var(--spacing);
    }
}
</style>
