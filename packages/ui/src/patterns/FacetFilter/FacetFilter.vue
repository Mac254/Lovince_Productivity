<script setup lang="ts" generic="T extends string | number">
import {
    LvCommandEmpty,
    LvCommandInput,
    LvCommandItem,
    LvCommandList,
    LvCommandRoot,
    LvCommandSeparator,
    LvPopoverContent,
    LvPopoverPortal,
    LvPopoverRoot,
    LvPopoverTrigger,
} from '@lovince/ui-vue';
import { computed, ref } from 'vue';
import { useLayer } from '../../composables/useLayer';
import Icon from '../../primitives/Icon/Icon.vue';

export interface FacetOption<V> {
    value: V;
    label: string;
}

/**
 * Lovince's faceted filter: "+ Label" until a value is chosen, then the value as a chip. The popover
 * holds a searchable list (its input carries the arrow-key handling); choosing the active value again (or "Clear filter") removes it. `''` is
 * "no filter", so options never include it.
 */
const props = defineProps<{ label: string; options: FacetOption<T>[] }>();
const model = defineModel<T | ''>({ default: '' });

const open = ref(false);
const layer = useLayer();
const active = computed(() => props.options.find((option) => option.value === model.value));

function choose(option: FacetOption<T>): void {
    model.value = option.value === model.value ? '' : option.value;
    open.value = false;
}

function clear(): void {
    model.value = '';
    open.value = false;
}
</script>

<template>
    <LvPopoverRoot v-model:open="open">
        <LvPopoverTrigger
            :aria-label="active ? `${label}: ${active.label}` : label"
            :class="[
                'group inline-flex h-(--control-sm) shrink-0 items-center gap-2 rounded-full border px-3 text-label font-semibold whitespace-nowrap transition-colors duration-(--duration-instant) focus-visible:outline-2 focus-visible:outline-focus-ring',
                active
                    ? 'border-border-strong bg-surface text-fg'
                    : 'border-dashed border-border-control text-fg-muted hover:border-border-strong hover:text-fg',
            ]"
        >
            <Icon :name="active ? 'filter' : 'add'" motion="pop" />
            {{ label }}
            <template v-if="active">
                <span class="h-4 w-px bg-border" aria-hidden="true" />
                <span class="max-w-40 truncate rounded-sm bg-highlight px-2 text-caption text-highlight-ink">{{
                    active.label
                }}</span>
            </template>
        </LvPopoverTrigger>
        <LvPopoverPortal :to="layer">
            <LvPopoverContent
                align="start"
                :side-offset="8"
                class="facet z-(--z-popover) w-64 rounded-md border border-border bg-surface-raised text-fg shadow-floating focus-visible:outline-none"
            >
                <LvCommandRoot>
                    <div class="border-b border-border p-1">
                        <LvCommandInput
                            :placeholder="label"
                            :aria-label="`Search ${label.toLowerCase()}`"
                            class="h-(--control-sm) w-full bg-transparent px-3 text-label placeholder:text-fg-subtle focus-visible:outline-none"
                        />
                    </div>
                    <LvCommandList :label="label" class="max-h-72 overflow-y-auto p-1">
                        <LvCommandItem
                            v-for="option in options"
                            :key="String(option.value)"
                            :value="option.label"
                            :aria-selected="option.value === model"
                            class="flex min-h-(--control-sm) cursor-pointer items-center justify-between gap-3 rounded-sm px-3 text-label font-medium outline-none select-none data-highlighted:bg-border"
                            @select="choose(option)"
                        >
                            <span class="truncate">{{ option.label }}</span>
                            <Icon v-if="option.value === model" name="check" class="text-highlight-fg" />
                        </LvCommandItem>
                        <LvCommandEmpty class="px-3 py-2 text-label text-fg-muted">No match.</LvCommandEmpty>
                        <template v-if="active">
                            <LvCommandSeparator class="my-1 h-px bg-border" />
                            <LvCommandItem
                                value="Clear filter"
                                class="flex min-h-(--control-sm) cursor-pointer items-center gap-3 rounded-sm px-3 text-label font-medium text-fg-muted outline-none select-none data-highlighted:bg-border data-highlighted:text-fg"
                                @select="clear"
                            >
                                <Icon name="close" />
                                Clear filter
                            </LvCommandItem>
                        </template>
                    </LvCommandList>
                </LvCommandRoot>
            </LvPopoverContent>
        </LvPopoverPortal>
    </LvPopoverRoot>
</template>

<style scoped>
.facet[data-state='open'] {
    animation: facet-in var(--duration-fast) var(--ease-out);
}

.facet[data-state='closed'] {
    animation: facet-out var(--duration-instant) var(--ease-in) forwards;
}

@keyframes facet-in {
    from {
        opacity: 0;
        translate: 0 calc(var(--spacing) * -1);
        scale: 0.98;
    }
}

@keyframes facet-out {
    to {
        opacity: 0;
    }
}
</style>
