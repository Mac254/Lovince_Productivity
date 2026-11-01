<script setup lang="ts">
import { LvAvatarFallback, LvAvatarImage, LvAvatarRoot } from '@lovince/ui-vue';
import { computed } from 'vue';

/** A photo when one is set (and loads), otherwise initials. Decorative: the name is announced elsewhere. */
const props = withDefaults(defineProps<{ name: string; src?: string | null; size?: 'sm' | 'md' | 'lg' | 'xl' }>(), {
    size: 'md',
    src: null,
});

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
    <LvAvatarRoot
        aria-hidden="true"
        :class="[
            'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-highlight bg-surface-raised font-display font-semibold text-highlight-fg',
            {
                sm: 'size-(--control-sm) text-caption',
                md: 'size-(--control-md) text-label',
                lg: 'size-(--control-lg) text-lead',
                xl: 'size-24 text-heading',
            }[size],
        ]"
    >
        <LvAvatarImage :src="src ?? undefined" class="size-full object-cover" />
        <LvAvatarFallback>{{ initials || '?' }}</LvAvatarFallback>
    </LvAvatarRoot>
</template>
