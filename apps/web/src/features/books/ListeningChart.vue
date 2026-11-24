<script setup lang="ts">
import type { ApexOptions } from 'apexcharts';
import { formatDate, formatMinutes } from '@/lib/dates';
import { VueApexCharts } from '@/features/metrics/apexChart';
import { useChartTheme } from '@/features/metrics/useChartTheme';
import type { ListeningWeek } from './listening';

/** Minutes listened per week as a soft area, so the rhythm of a show reads at a glance. */
const props = defineProps<{ weeks: ListeningWeek[] }>();
const { colors, base } = useChartTheme();

const series = computed(() => [{ name: 'Listened', data: props.weeks.map((w) => ({ x: w.start, y: w.minutes })) }]);

const options = computed<ApexOptions>(() => ({
    ...base,
    chart: { ...base.chart, id: 'listening', sparkline: { enabled: false } },
    legend: { show: false },
    colors: [colors.series1],
    stroke: { width: 2, curve: 'monotoneCubic', lineCap: 'round' },
    fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 0, opacityFrom: 0.35, opacityTo: 0, stops: [0, 100] },
    },
    markers: { size: 0, strokeColors: colors.surface, strokeWidth: 2, hover: { size: 5 } },
    xaxis: {
        type: 'datetime',
        labels: { datetimeUTC: false, format: 'd MMM' },
        axisBorder: { show: false },
        axisTicks: { show: false },
        tooltip: { enabled: false },
    },
    yaxis: {
        min: 0,
        tickAmount: 3,
        labels: { formatter: (v: number) => (v >= 60 ? `${Math.round(v / 60)}h` : `${Math.round(v)}m`) },
    },
    grid: { ...base.grid, xaxis: { lines: { show: false } } },
    tooltip: {
        ...base.tooltip,
        x: { formatter: (v: number) => `Week of ${formatDate(new Date(v).toISOString().slice(0, 10))}` },
        y: {
            formatter: (v: number, opts?: { dataPointIndex?: number }) => {
                const week = props.weeks[opts?.dataPointIndex ?? 0];
                const episodes = week?.episodes ?? 0;
                return `${formatMinutes(v) || '0m'} · ${episodes} ${episodes === 1 ? 'episode' : 'episodes'}`;
            },
        },
    },
}));
</script>

<template>
    <VueApexCharts type="area" height="200" :options="options" :series="series" />
</template>
