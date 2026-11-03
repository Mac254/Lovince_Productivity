<script setup lang="ts">
import { LvTimeline } from '@lovince/ui-vue';

withDefaults(defineProps<{ reveal?: boolean }>(), { reveal: false });
</script>

<template>
    <LvTimeline as="ol" orientation="vertical" align="start" :reveal="reveal" class="flex flex-col">
        <slot />
    </LvTimeline>
</template>
