<script setup lang="ts">
import {
    LvCalendarCell,
    LvCalendarDay,
    LvCalendarGrid,
    LvCalendarHeadCell,
    LvCalendarHeading,
    LvCalendarNext,
    LvCalendarPrev,
    LvCalendarRoot,
    LvPopoverContent,
    LvPopoverPortal,
    LvPopoverRoot,
    LvPopoverTrigger,
} from '@lovince/ui-vue';
import { computed, ref } from 'vue';
import { useLayer } from '../../composables/useLayer';
import Icon from '../../primitives/Icon/Icon.vue';

/**
 * A date range as a facet chip, for filter bars beside FacetFilter: "+ Label" until a range is
 * chosen, then the range. Lovince's range calendar picks the start, then the end, and closes.
 * Dates are ISO `YYYY-MM-DD`; `''` means no bound.
 */
const props = defineProps<{ label: string; min?: string; max?: string }>();
const start = defineModel<string>('start', { default: '' });
const end = defineModel<string>('end', { default: '' });

const open = ref(false);
const layer = useLayer();

const toDate = (iso: string) => new Date(Number(iso.slice(0, 4)), Number(iso.slice(5, 7)) - 1, Number(iso.slice(8, 10)));
const format = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
const summary = computed(() => {
    if (start.value && end.value) return format.formatRange(toDate(start.value), toDate(end.value));
    if (start.value) return `From ${format.format(toDate(start.value))}`;
    if (end.value) return `Until ${format.format(toDate(end.value))}`;
    return '';
});

function setStart(value: string | undefined): void {
    start.value = value ?? '';
    end.value = '';
}

function setEnd(value: string | undefined): void {
    end.value = value ?? '';
    if (value) open.value = false;
}

function clear(): void {
    start.value = '';
    end.value = '';
    open.value = false;
}

const pager =
    'inline-flex size-(--control-sm) items-center justify-center rounded-md text-fg-muted transition-colors duration-(--duration-fast) hover:bg-border hover:text-fg focus-visible:outline-2 focus-visible:outline-focus-ring disabled:opacity-40';
</script>

<template>
    <LvPopoverRoot v-model:open="open">
        <LvPopoverTrigger
            :aria-label="summary ? `${props.label}: ${summary}` : props.label"
            :class="[
                'group inline-flex h-(--control-sm) shrink-0 items-center gap-2 rounded-full border px-3 text-label font-semibold whitespace-nowrap transition-colors duration-(--duration-instant) focus-visible:outline-2 focus-visible:outline-focus-ring',
                summary
                    ? 'border-border-strong bg-surface text-fg'
                    : 'border-dashed border-border-control text-fg-muted hover:border-border-strong hover:text-fg',
            ]"
        >
            <Icon :name="summary ? 'calendar' : 'add'" motion="pop" />
            {{ props.label }}
            <template v-if="summary">
                <span class="h-4 w-px bg-border" aria-hidden="true" />
                <span class="rounded-sm bg-highlight px-2 text-caption text-highlight-ink tabular">{{ summary }}</span>
            </template>
        </LvPopoverTrigger>
        <LvPopoverPortal :to="layer">
            <LvPopoverContent
                align="start"
                :side-offset="8"
                class="range-menu z-(--z-popover) flex flex-col gap-3 rounded-lg border border-border bg-surface-raised p-3 text-fg shadow-floating focus-visible:outline-none"
            >
                <LvCalendarRoot
                    mode="range"
                    :start="start || undefined"
                    :end="end || undefined"
                    :min="min"
                    :max="max"
                    @update:start="setStart"
                    @update:end="setEnd"
                >
                    <template #default="{ months, weekdays }">
                        <div v-for="month in months" :key="month.month" class="flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-2">
                                <LvCalendarPrev :class="pager"><Icon name="chevron-left" /></LvCalendarPrev>
                                <LvCalendarHeading :month="month.month" as="p" class="text-label font-semibold" />
                                <LvCalendarNext :class="pager"><Icon name="chevron-right" /></LvCalendarNext>
                            </div>
                            <LvCalendarGrid :month="month.month" class="border-collapse">
                                <thead>
                                    <tr>
                                        <LvCalendarHeadCell
                                            v-for="day in weekdays"
                                            :key="day.long"
                                            :label="day.long"
                                            class="pb-1 text-caption font-semibold text-fg-muted"
                                        >
                                            {{ day.short }}
                                        </LvCalendarHeadCell>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(week, w) in month.weeks" :key="w">
                                        <LvCalendarCell v-for="day in week" :key="day.date" :date="day.date" class="p-0">
                                            <LvCalendarDay
                                                :date="day.date"
                                                :month="month.month"
                                                class="inline-flex size-(--control-sm) items-center justify-center rounded-md text-label font-medium tabular transition-colors duration-(--duration-fast) hover:bg-border focus-visible:outline-2 focus-visible:outline-focus-ring data-outside:text-fg-subtle data-today:text-highlight-fg data-unavailable:pointer-events-none data-unavailable:opacity-40 data-selected:bg-highlight data-selected:text-highlight-ink"
                                            />
                                        </LvCalendarCell>
                                    </tr>
                                </tbody>
                            </LvCalendarGrid>
                        </div>
                    </template>
                </LvCalendarRoot>
                <div class="flex items-center justify-between gap-3 border-t border-border pt-3">
                    <span class="text-caption text-fg-muted">{{ start && !end ? 'Now pick the last day' : 'Pick the first day' }}</span>
                    <button
                        v-if="start || end"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-sm px-2 text-label font-medium text-fg-muted hover:text-fg focus-visible:outline-2 focus-visible:outline-focus-ring"
                        @click="clear"
                    >
                        <Icon name="close" />
                        Clear
                    </button>
                </div>
            </LvPopoverContent>
        </LvPopoverPortal>
    </LvPopoverRoot>
</template>

<style scoped>
.range-menu :deep([data-range='middle']) {
    border-radius: 0;
    background-color: var(--color-border);
    color: var(--color-fg);
}

.range-menu[data-state='open'] {
    animation: range-in var(--duration-fast) var(--ease-out);
}

.range-menu[data-state='closed'] {
    animation: range-out var(--duration-instant) var(--ease-in) forwards;
}

@keyframes range-in {
    from {
        opacity: 0;
        scale: 0.98;
    }
}

@keyframes range-out {
    to {
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .range-menu[data-state] {
        animation: none;
    }
}
</style>
