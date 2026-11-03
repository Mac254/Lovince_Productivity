<script setup lang="ts">
import { computed } from 'vue';
import { LvCheckmark, LvLoading, LvMenuToClose, LvPlusToClose } from '@lovince/icons/vue';

export type AnimatedIconKind = 'menu' | 'add' | 'check' | 'loading';

const props = withDefaults(
    defineProps<{ kind: AnimatedIconKind; active?: boolean; size?: 'sm' | 'md' | 'lg'; label?: string }>(),
    { active: false, size: 'sm' },
);

const component = { menu: LvMenuToClose, add: LvPlusToClose, check: LvCheckmark, loading: LvLoading };
const state = computed(() =>
    props.kind === 'check' ? { checked: props.active } : props.kind === 'loading' ? {} : { open: props.active },
);
</script>

<template>
    <component
        :is="component[kind]"
        v-bind="state"
        :label="label"
        size="100%"
        :class="['shrink-0', { sm: 'size-(--icon-sm)', md: 'size-(--icon-md)', lg: 'size-(--icon-lg)' }[size]]"
    />
</template>
