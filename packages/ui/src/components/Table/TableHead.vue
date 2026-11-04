<script setup lang="ts">
import { LvTableHead } from '@lovince/ui-vue';
import { alignClasses, type TableAlign } from './align';

withDefaults(defineProps<{ scope?: 'col' | 'row'; align?: TableAlign }>(), { scope: 'col', align: 'start' });
</script>

<template>
    <LvTableHead
        :scope="scope"
        :align="align"
        :class="['px-3 py-2 first:pl-0 last:pr-0', alignClasses[align], scope === 'row' ? 'font-medium text-fg' : 'font-semibold']"
    >
        <slot />
    </LvTableHead>
</template>
