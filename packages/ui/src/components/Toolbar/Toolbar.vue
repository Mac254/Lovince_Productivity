<script setup lang="ts">
import { LvToolbarRoot } from '@lovince/ui-vue';

defineProps<{ label: string; disabled?: boolean }>();
</script>

<template>
    <LvToolbarRoot :label="label" :disabled="disabled" class="flex flex-wrap items-center gap-1">
        <slot />
    </LvToolbarRoot>
</template>
