<script setup lang="ts">
import type { FocusMetrics } from '@focus/types';
import { streakLabel } from '@/features/clock/focusSummary';
import { formatMinutes } from '@/lib/dates';

const props = defineProps<{ focus: FocusMetrics; days: number }>();

const peak = computed(() => Math.max(1, ...props.focus.by_hour));
const hours = computed(() =>
    props.focus.by_hour.map((minutes, hour) => ({ hour, minutes, share: (minutes / peak.value) * 100 })),
);

const change = computed(() => {
    const { minutes, minutes_previous: previous } = props.focus;
    if (!previous) return null;
    const value = Math.round(((minutes - previous) / previous) * 100);
    return `${value >= 0 ? '+' : ''}${value}% vs previous ${props.days} days`;
});

const stats = computed(() => [
    {
        label: 'Focused',
        value: formatMinutes(props.focus.minutes) || '0m',
        hint: change.value ?? `${props.focus.sessions} sessions`,
    },
    {
        label: 'Per day',
        value: formatMinutes(props.focus.per_day) || '0m',
        hint: `${props.focus.completed} of ${props.focus.sessions} ran to the end`,
    },
    { label: 'Focus streak', value: String(props.focus.streak), hint: streakLabel(props.focus.streak) },
    {
        label: 'Goal met',
        value: props.focus.goal_minutes ? `${props.focus.goal_days}/${props.days}` : '—',
        hint: props.focus.goal_minutes
            ? `Days with ${formatMinutes(props.focus.goal_minutes)} or more`
            : 'Set a daily goal in Settings',
    },
]);

function hourLabel(hour: number): string {
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric' }).format(new Date(2000, 0, 1, hour));
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="stat in stats" :key="stat.label" class="flex flex-col gap-1">
                <dt class="text-label text-fg-muted">{{ stat.label }}</dt>
                <dd class="font-display text-heading font-semibold tabular">{{ stat.value }}</dd>
                <dd class="text-caption text-fg-muted">{{ stat.hint }}</dd>
            </div>
        </dl>

        <div class="flex flex-col gap-3">
            <div class="flex items-baseline justify-between gap-3">
                <Text role="caption" tone="muted" weight="semibold">By time of day</Text>
                <Text v-if="focus.best_hour !== null" role="caption" tone="muted">
                    Most focus around {{ hourLabel(focus.best_hour) }}
                </Text>
            </div>
            <ol class="grid h-32 grid-cols-24 items-end gap-1" aria-label="Focus minutes by starting hour">
                <li
                    v-for="item in hours"
                    :key="item.hour"
                    class="flex h-full flex-col justify-end"
                    :title="`${hourLabel(item.hour)}: ${formatMinutes(item.minutes) || '0m'}`"
                >
                    <span class="sr-only">{{ hourLabel(item.hour) }}: {{ formatMinutes(item.minutes) || 'none' }}</span>
                    <span
                        :class="['hour-bar rounded-sm', item.hour === focus.best_hour ? 'bg-highlight' : 'bg-chart-3']"
                        :style="{ height: `${item.minutes ? Math.max(4, item.share) : 2}%` }"
                        aria-hidden="true"
                    />
                </li>
            </ol>
            <div class="grid grid-cols-4 text-caption text-fg-muted" aria-hidden="true">
                <span v-for="hour in [0, 6, 12, 18]" :key="hour">{{ hourLabel(hour) }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.hour-bar {
    transition: height var(--duration-normal) var(--ease-out);
}
</style>
