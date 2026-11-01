<script setup lang="ts">
import { useKinetic, type KineticEffect } from '@focus/motion';
import { LvHeading, type TextRole, type TextWrap } from '@lovince/ui-vue';
import { computed, ref } from 'vue';

type HeadingRole = 'display-lg' | 'display-md' | 'display-sm' | 'heading' | 'title' | 'lead';

const props = withDefaults(
    defineProps<{
        level?: 1 | 2 | 3 | 4;
        role?: HeadingRole;
        uppercase?: boolean;
        wrap?: TextWrap;
        truncate?: boolean | number;
        kinetic?: KineticEffect;
    }>(),
    { level: 2, role: 'heading', wrap: 'balance', truncate: false, kinetic: undefined },
);

const variants: Record<HeadingRole, TextRole> = {
    'display-lg': 'display-lg',
    'display-md': 'display-md',
    'display-sm': 'display-sm',
    heading: 'headline-md',
    title: 'title-lg',
    lead: 'title-sm',
};

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

const el = ref<{ $el: HTMLElement } | null>(null);
const root = computed(() => el.value?.$el ?? null);
useKinetic(root, () => props.kinetic);
</script>

<template>
    <LvHeading
        ref="el"
        :level="level"
        :size="variants[role]"
        :wrap="wrap"
        :truncate="truncate"
        :uppercase="uppercase"
        :data-kinetic="kinetic"
        :class="['font-display tracking-tight', roleClass]"
    >
        <slot />
    </LvHeading>
</template>
