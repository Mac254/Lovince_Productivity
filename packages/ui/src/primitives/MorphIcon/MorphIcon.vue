<script setup lang="ts">
import { LvMorphIcon } from '@lovince/icons/vue';
import type { IconName as MorphIconName } from '@lovince/icons/vue';

export type { MorphIconName };

withDefaults(defineProps<{ name: MorphIconName; size?: 'sm' | 'md' | 'lg'; label?: string }>(), { size: 'sm' });
</script>

<template>
    <LvMorphIcon
        :name="name"
        :label="label"
        size="100%"
        :class="['shrink-0', { sm: 'size-(--icon-sm)', md: 'size-(--icon-md)', lg: 'size-(--icon-lg)' }[size]]"
    />
</template>
