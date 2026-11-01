<script setup lang="ts">
import { useKinetic, type KineticEffect } from '@focus/motion';
import { LvHeading, LvText } from '@lovince/ui-vue';
import { computed, ref, useSlots } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import Button from '../../components/Button/Button.vue';

/**
 * Page title, optional eyebrow (badges) and description, the page's actions on the right,
 * and on detail pages a Back button at the far right.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        titleId?: string;
        back?: { to: RouteLocationRaw; label: string };
        kinetic?: KineticEffect | 'none';
    }>(),
    { description: undefined, titleId: undefined, back: undefined, kinetic: 'blur' },
);

const heading = ref<{ $el: HTMLElement } | null>(null);
const slots = useSlots();
const effect = computed(() => (props.kinetic !== 'none' && !slots.title ? props.kinetic : undefined));
useKinetic(
    computed(() => heading.value?.$el ?? null),
    () => effect.value,
);
</script>

<template>
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <div v-if="$slots.eyebrow" class="mb-2 flex flex-wrap items-center gap-2"><slot name="eyebrow" /></div>
            <LvHeading
                :id="titleId"
                ref="heading"
                :key="title"
                :level="1"
                size="display-sm"
                wrap="balance"
                :data-kinetic="effect"
                class="font-display text-heading font-semibold tracking-tight break-words sm:text-display-sm"
            >
                <slot name="title">{{ title }}</slot>
            </LvHeading>
            <LvText
                v-if="description || $slots.description"
                as="p"
                variant="body-md"
                wrap="pretty"
                class="mt-1 text-body text-fg-muted"
            >
                <slot name="description">{{ description }}</slot>
            </LvText>
        </div>
        <div v-if="$slots.actions || back" class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
            <slot name="actions" />
            <Button v-if="back" variant="outline" icon-start="arrow-left" :to="back.to" :aria-label="back.label">
                Back
            </Button>
        </div>
    </header>
</template>
