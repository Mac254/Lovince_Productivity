<script setup lang="ts">
import { LvVisuallyHidden } from '@lovince/ui-vue';

withDefaults(defineProps<{ as?: string }>(), { as: 'span' });
</script>

<template>
    <LvVisuallyHidden :as="as"><slot /></LvVisuallyHidden>
</template>
