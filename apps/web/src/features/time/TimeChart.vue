<script setup lang="ts">
import type { TimeCategory, TimeRange, TimeSummary } from '@focus/types';
import type { ApexOptions } from 'apexcharts';
import { formatDate, formatMinutes } from '@/lib/dates';
import { VueApexCharts } from '@/features/metrics/apexChart';
import { useChartTheme } from '@/features/metrics/useChartTheme';
import { categoryColor } from './tones';

/** Time per hour (a day) or per day (a week or month), stacked by category like a screen-time report. */
const props = defineProps<{ summary: TimeSummary; categories: TimeCategory[]; range: TimeRange }>();
const { colors, base } = useChartTheme();
const css = getComputedStyle(document.documentElement);

const series = computed(() =>
    props.categories.map((category) => ({
        name: category.name,
        data: props.summary.buckets.map((bucket) => bucket.minutes[category.key] ?? 0),
    })),
);

function hourLabel(hour: number): string {
    if (hour % 6) return '';
    return hour === 0 ? '12a' : hour === 12 ? '12p' : hour < 12 ? `${hour}a` : `${hour - 12}p`;
}

const labels = computed(() =>
    props.summary.buckets.map((bucket) => {
        if (props.range === 'day') return hourLabel(Number(bucket.label));
        if (props.range === 'week') return formatDate(bucket.label, { weekday: 'short' });
        return formatDate(bucket.label, { day: 'numeric' });
    }),
);

const options = computed<ApexOptions>(() => ({
    ...base,
    chart: { ...base.chart, id: 'time-chart', stacked: true },
    legend: { show: false },
    colors: props.categories.map((category) => categoryColor(category, css)),
    plotOptions: {
        bar: {
            columnWidth: props.range === 'month' ? '70%' : '55%',
            borderRadius: 4,
            borderRadiusApplication: 'end',
            borderRadiusWhenStacked: 'last',
        },
    },
    stroke: { show: true, width: 2, colors: [colors.surface] },
    xaxis: {
        categories: labels.value,
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { rotate: 0, hideOverlappingLabels: true },
        tooltip: { enabled: false },
    },
    yaxis: {
        min: 0,
        max: props.range === 'day' ? 60 : undefined,
        tickAmount: props.range === 'day' ? 2 : 4,
        forceNiceScale: props.range !== 'day',
        labels: {
            formatter: (v: number) =>
                props.range === 'day' ? `${Math.round(v)}m` : v ? `${Math.round(v / 60)}h` : '0',
        },
    },
    grid: { ...base.grid, xaxis: { lines: { show: false } } },
    tooltip: {
        ...base.tooltip,
        shared: true,
        intersect: false,
        x: {
            formatter: (_v: number, opts?: { dataPointIndex?: number }) => {
                const label = props.summary.buckets[opts?.dataPointIndex ?? 0]?.label ?? '';
                if (props.range !== 'day')
                    return formatDate(label, { weekday: 'short', day: 'numeric', month: 'short' });
                const hour = Number(label);
                return `${hour % 12 || 12}:00 ${hour < 12 ? 'am' : 'pm'}`;
            },
        },
        y: { formatter: (v: number) => formatMinutes(v) || '0m' },
    },
}));
</script>

<template>
    <VueApexCharts type="bar" height="240" :options="options" :series="series" />
</template>
