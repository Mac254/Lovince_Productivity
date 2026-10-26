<script setup lang="ts">
import { computed, ref, watch } from 'vue';

/** A photo when one is set (and loads), otherwise initials. Decorative: the name is announced elsewhere. */
const props = withDefaults(defineProps<{ name: string; src?: string | null; size?: 'sm' | 'md' | 'lg' | 'xl' }>(), {
    size: 'md',
    src: null,
});

const failed = ref(false);
watch(
    () => props.src,
    () => (failed.value = false),
);

const initials = computed(() =>
    props.name
        .split(/\s+/)
        .filter(Boolean)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);
</script>

<template>
    <span
        aria-hidden="true"
        :class="[
            'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-highlight bg-surface-inverse font-display font-semibold text-highlight',
            {
                sm: 'size-(--control-sm) text-caption',
                md: 'size-(--control-md) text-label',
                lg: 'size-(--control-lg) text-lead',
                xl: 'size-24 text-heading',
            }[size],
        ]"
    >
        <img v-if="src && !failed" :src="src" alt="" class="size-full object-cover" @error="failed = true" />
        <template v-else>{{ initials || '?' }}</template>
    </span>
</template>
