<script setup lang="ts">
import { useKinetic, type KineticEffect } from '@focus/motion';
import { LvText, type TextColor, type TextRole, type TextWrap } from '@lovince/ui-vue';
import { computed, ref } from 'vue';
import { useSurfaceTone } from '../../composables/useSurfaceTone';

export type TextTone = 'default' | 'muted' | 'subtle' | 'accent' | 'highlight' | 'danger' | 'inverse';
type Role = 'caption' | 'label' | 'body' | 'lead' | 'title';

const props = withDefaults(
    defineProps<{
        as?: string;
        role?: Role;
        tone?: TextTone;
        weight?: 'medium' | 'semibold' | 'bold';
        wrap?: TextWrap;
        tabular?: boolean;
        truncate?: boolean | number;
        uppercase?: boolean;
        kinetic?: KineticEffect;
    }>(),
    { as: 'p', role: 'body', tone: 'default', weight: 'medium', wrap: undefined, truncate: false, kinetic: undefined },
);

const variants: Record<Role, TextRole> = {
    caption: 'body-sm',
    label: 'label-lg',
    body: 'body-md',
    lead: 'body-lg',
    title: 'title-md',
};

const surface = useSurfaceTone();

const color = computed<TextColor | undefined>(() =>
    surface === 'default' && props.tone !== 'highlight' ? props.tone : undefined,
);

const toneClass = computed(() => {
    if (surface === 'highlight') return 'text-highlight-ink';
    if (surface === 'accent') return 'text-accent-ink';
    return {
        default: 'text-fg',
        muted: 'text-fg-muted',
        subtle: 'text-fg-subtle',
        accent: 'text-accent-fg',
        highlight: 'text-highlight-fg',
        danger: 'text-danger-fg',
        inverse: 'text-fg-inverse',
    }[props.tone];
});

const el = ref<{ $el: HTMLElement } | null>(null);
const root = computed(() => el.value?.$el ?? null);
useKinetic(root, () => props.kinetic);
</script>

<template>
    <LvText
        ref="el"
        :as="as"
        :variant="variants[role]"
        :color="color"
        :weight="weight"
        :wrap="wrap"
        :truncate="truncate"
        :uppercase="uppercase"
        :data-kinetic="kinetic"
        :class="[
            {
                caption: 'text-caption',
                label: 'text-label',
                body: 'text-body',
                lead: 'text-lead',
                title: 'text-title',
            }[role],
            { medium: 'font-medium', semibold: 'font-semibold', bold: 'font-bold' }[weight],
            toneClass,
            tabular && 'tabular',
        ]"
    >
        <slot />
    </LvText>
</template>
