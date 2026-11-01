<script setup lang="ts">
import { LvTableRow } from '@lovince/ui-vue';

defineProps<{ selected?: boolean }>();
</script>

<template>
    <LvTableRow :selected="selected" :class="selected && 'bg-surface-raised'">
        <slot />
    </LvTableRow>
</template>
