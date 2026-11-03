<script setup lang="ts">
import { LvTimelineConnector, LvTimelineContent, LvTimelineItem, LvTimelineMarker, LvTimelineTime } from '@lovince/ui-vue';
import type { BadgeTone } from '../../components/Badge/tones';
import type { IconName } from '../../icons/registry';
import Icon from '../../primitives/Icon/Icon.vue';

withDefaults(
    defineProps<{ index: number; icon: IconName; tone?: BadgeTone; datetime?: string; last?: boolean }>(),
    { tone: 'neutral', datetime: undefined, last: false },
);

const markers: Record<BadgeTone, string> = {
    neutral: 'bg-surface-raised text-fg-muted ring-border',
    success: 'bg-success-subtle text-success-fg ring-success-border',
    warning: 'bg-warning-subtle text-warning-fg ring-warning-border',
    danger: 'bg-danger-subtle text-danger-fg ring-danger-border',
    info: 'bg-info-subtle text-info-fg ring-info-border',
    accent: 'bg-accent-solid text-accent-ink ring-border',
    highlight: 'bg-highlight text-highlight-ink ring-highlight',
    inverse: 'bg-surface-inverse text-fg-inverse ring-surface-inverse',
};
</script>

<template>
    <LvTimelineItem as="li" :index="index" class="flex gap-4">
        <div class="flex flex-col items-center">
            <LvTimelineMarker
                :class="[
                    'inline-flex size-(--control-sm) shrink-0 items-center justify-center rounded-full ring-1',
                    markers[tone],
                ]"
            >
                <Icon :name="icon" />
            </LvTimelineMarker>
            <LvTimelineConnector v-if="!last" aria-hidden="true" class="my-2 w-px flex-1 bg-border" />
        </div>
        <LvTimelineContent :class="['flex min-w-0 flex-1 flex-col gap-2', !last && 'pb-6']">
            <LvTimelineTime
                v-if="datetime || $slots.time"
                :datetime="datetime"
                class="pt-2 text-label text-fg-muted tabular"
            >
                <slot name="time">{{ datetime }}</slot>
            </LvTimelineTime>
            <slot />
        </LvTimelineContent>
    </LvTimelineItem>
</template>
