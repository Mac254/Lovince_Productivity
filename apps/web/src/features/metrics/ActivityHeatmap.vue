<script setup lang="ts">
import type { Metrics } from '@focus/types';
import type { ApexOptions } from 'apexcharts';
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { formatDate, todayIso } from '@/lib/dates';
import { useChartTheme } from './useChartTheme';

/**
 * Activity per day over the last six months (tasks completed plus work logged): weeks across, weekdays down (Monday first).
 * One hue, light to dark; days still to come are left empty.
 */
const props = defineProps<{ days: Metrics['heatmap'] }>();
const { colors, base } = useChartTheme();

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const today = todayIso();

const weeks = computed(() => {
    const chunks: Metrics['heatmap'][] = [];
    for (let i = 0; i < props.days.length; i += 7) chunks.push(props.days.slice(i, i + 7));
    return chunks;
});

/** Only weeks containing the 1st of a month get an axis label, so labels never collide. */
const monthStarts = computed(
    () =>
        new Set(
            weeks.value
                .filter((week) => week.some((day) => day.date.endsWith('-01')) || week === weeks.value[0])
                .map((week) => (week[0] ? formatDate(week[0].date, { day: 'numeric', month: 'short' }) : '')),
        ),
);

const max = computed(() => Math.max(1, ...props.days.map((d) => d.count)));

/** ApexCharts draws the first series at the bottom, so weekdays are reversed to read Monday-first. */
const series = computed(() =>
    WEEKDAYS.map((name, weekday) => ({
        name,
        data: weeks.value.map((week) => {
            const day = week[weekday];
            return {
                x: week[0] ? formatDate(week[0].date, { day: 'numeric', month: 'short' }) : '',
                y: day && day.date <= today ? day.count : -1,
                date: day?.date ?? '',
                completed: day?.completed ?? 0,
                logged: day?.logged ?? 0,
            };
        }),
    })).reverse(),
);

const ranges = computed(() => {
    const step = Math.max(1, Math.ceil(max.value / 4));
    return [
        { from: -1, to: -1, color: colors.surface, name: 'Upcoming' },
        { from: 0, to: 0, color: colors.heat[0], name: 'None' },
        { from: 1, to: step, color: colors.heat[1] },
        { from: step + 1, to: step * 2, color: colors.heat[2] },
        { from: step * 2 + 1, to: step * 3, color: colors.heat[3] },
        { from: step * 3 + 1, to: Number.MAX_SAFE_INTEGER, color: colors.heat[4] },
    ];
});

const options = computed<ApexOptions>(() => ({
    ...base,
    legend: { show: false },
    plotOptions: {
        heatmap: { radius: 2, enableShades: false, useFillColorAsStroke: false, colorScale: { ranges: ranges.value } },
    },
    stroke: { width: 2, colors: [colors.surface] },
    xaxis: {
        labels: {
            rotate: 0,
            hideOverlappingLabels: false,
            formatter: (value: string) => (monthStarts.value.has(value) ? value : ''),
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
        tooltip: { enabled: false },
    },
    yaxis: { labels: { minWidth: 40, maxWidth: 40, style: { colors: colors.muted } } },
    grid: { show: false, padding: { left: 16, right: 0 } },
    tooltip: {
        ...base.tooltip,
        custom: ({ seriesIndex, dataPointIndex }: { seriesIndex: number; dataPointIndex: number }) => {
            const point = series.value[seriesIndex]?.data[dataPointIndex];
            if (!point || point.y < 0) return '';
            const label = formatDate(point.date, { weekday: 'short', day: 'numeric', month: 'short' });
            const logged = point.logged ? ` · <strong>${point.logged}</strong> logged` : '';
            return `<div class="apex-tip"><strong>${point.completed}</strong> completed${logged}<br><span>${label}</span></div>`;
        },
    },
}));
</script>

<template>
    <div>
        <VueApexCharts type="heatmap" height="240" :options="options" :series="series" />
        <div class="mt-2 flex items-center justify-end gap-2 text-caption text-fg-muted" aria-hidden="true">
            <span>Less</span>
            <span
                v-for="n in 5"
                :key="n"
                :class="['size-3 rounded-sm', ['bg-heat-0', 'bg-heat-1', 'bg-heat-2', 'bg-heat-3', 'bg-heat-4'][n - 1]]"
            />
            <span>More</span>
        </div>
    </div>
</template>
