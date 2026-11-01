<script setup lang="ts">
import { LvTableCell } from '@lovince/ui-vue';
import { alignClasses, type TableAlign } from './align';

withDefaults(defineProps<{ align?: TableAlign; numeric?: boolean }>(), { align: 'start' });
</script>

<template>
    <LvTableCell :align="align" :class="['py-2', alignClasses[align], numeric && 'tabular']">
        <slot />
    </LvTableCell>
</template>
