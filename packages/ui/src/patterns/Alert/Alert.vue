<script setup lang="ts">
import { computed } from 'vue';
import type { IconName } from '../../icons/registry';
import Icon from '../../primitives/Icon/Icon.vue';

const props = withDefaults(defineProps<{ tone?: 'info' | 'warning' | 'danger'; icon?: IconName; banner?: boolean }>(), {
    tone: 'info',
    icon: undefined,
    banner: false,
});

const tones = {
    info: { box: 'border-info-border bg-info-subtle', icon: 'text-info-fg', glyph: 'info' },
    warning: { box: 'border-warning-border bg-warning-subtle', icon: 'text-warning-fg', glyph: 'warning' },
    danger: { box: 'border-danger-border bg-danger-subtle', icon: 'text-danger-fg', glyph: 'alert-circle' },
} as const;

const style = computed(() => tones[props.tone]);
</script>

<template>
    <div
        :role="tone === 'danger' ? 'alert' : 'status'"
        :class="[
            'alert flex items-start gap-3 px-4 py-3 text-label text-fg',
            style.box,
            banner ? 'justify-center border-b' : 'rounded-md border',
        ]"
    >
        <Icon :name="icon ?? style.glyph" :class="['shrink-0', style.icon]" />
        <div class="min-w-0 font-semibold"><slot /></div>
    </div>
</template>

<style scoped>
.alert {
    animation: alert-in var(--duration-normal) var(--ease-out);
}

@keyframes alert-in {
    from {
        opacity: 0;
        translate: 0 calc(var(--spacing) * -1);
    }
}
</style>
