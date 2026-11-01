<script setup lang="ts">
import type { MetricInsight } from '@focus/types';
import type { IconName } from '@focus/ui';

defineProps<{ insights: MetricInsight[] }>();

const tones: Record<MetricInsight['tone'], { icon: IconName; class: string }> = {
    positive: { icon: 'check-circle', class: 'text-success-fg' },
    neutral: { icon: 'info', class: 'text-fg-muted' },
    warning: { icon: 'warning', class: 'text-warning-fg' },
};
</script>

<template>
    <ul class="flex flex-col gap-3">
        <li v-for="insight in insights" :key="insight.key" class="flex items-start gap-3">
            <Icon :name="tones[insight.tone].icon" :class="['mt-1 shrink-0', tones[insight.tone].class]" />
            <Text>{{ insight.text }}</Text>
        </li>
    </ul>
</template>
