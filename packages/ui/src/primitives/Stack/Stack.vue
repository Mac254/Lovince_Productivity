<script setup lang="ts">
import { computed } from 'vue';

type Gap = 0 | 1 | 2 | 3 | 4 | 6 | 8 | 12;

const props = withDefaults(
    defineProps<{
        as?: string;
        direction?: 'column' | 'row';
        gap?: Gap;
        align?: 'start' | 'center' | 'end' | 'baseline' | 'stretch';
        justify?: 'start' | 'center' | 'end' | 'between';
        wrap?: boolean;
    }>(),
    { as: 'div', direction: 'column', gap: 4, align: undefined, justify: undefined },
);

const gaps: Record<Gap, string> = {
    0: 'gap-0',
    1: 'gap-1',
    2: 'gap-2',
    3: 'gap-3',
    4: 'gap-4',
    6: 'gap-6',
    8: 'gap-8',
    12: 'gap-12',
};
const aligns = {
    start: 'items-start',
    center: 'items-center',
    end: 'items-end',
    baseline: 'items-baseline',
    stretch: 'items-stretch',
};
const justifies = { start: 'justify-start', center: 'justify-center', end: 'justify-end', between: 'justify-between' };

const classes = computed(() => [
    'flex',
    props.direction === 'row' ? 'flex-row' : 'flex-col',
    gaps[props.gap],
    props.align && aligns[props.align],
    props.justify && justifies[props.justify],
    props.wrap && 'flex-wrap',
]);
</script>

<template>
    <component :is="as" :class="classes"><slot /></component>
</template>
