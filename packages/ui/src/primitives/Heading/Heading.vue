<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        level?: 1 | 2 | 3 | 4;
        role?: 'display-lg' | 'display-md' | 'display-sm' | 'heading' | 'title' | 'lead';
        uppercase?: boolean;
    }>(),
    { level: 2, role: 'heading' },
);

const roleClass = computed(
    () =>
        ({
            'display-lg': 'text-display-sm sm:text-display-lg font-semibold',
            'display-md': 'text-display-sm sm:text-display-md font-semibold',
            'display-sm': 'text-display-sm font-semibold',
            heading: 'text-heading font-medium',
            title: 'text-title font-medium',
            lead: 'text-lead font-medium',
        })[props.role],
);
</script>

<template>
    <component
        :is="`h${level}`"
        :class="['font-display tracking-tight text-balance', roleClass, uppercase && 'uppercase']"
    >
        <slot />
    </component>
</template>
