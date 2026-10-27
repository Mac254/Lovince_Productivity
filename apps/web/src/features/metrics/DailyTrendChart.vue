<script setup lang="ts">
import type { Metrics } from '@focus/types';
import type { ApexOptions } from 'apexcharts';
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { useChartTheme } from './useChartTheme';

/** Tasks completed (the measure, green with a light wash) against tasks created (grey) and work logged (blue). */
const props = defineProps<{ daily: Metrics['daily'] }>();
const { colors, base } = useChartTheme();

const series = computed(() => [
    { name: 'Completed', type: 'area', data: props.daily.map((d) => ({ x: d.date, y: d.completed })) },
    { name: 'Created', type: 'line', data: props.daily.map((d) => ({ x: d.date, y: d.created })) },
    { name: 'Work logged', type: 'line', data: props.daily.map((d) => ({ x: d.date, y: d.logged })) },
]);

const options = computed<ApexOptions>(() => ({
    ...base,
    chart: { ...base.chart, id: 'daily-trend' },
    colors: [colors.series1, colors.series2, colors.series3],
    stroke: { width: [2, 2, 2], curve: 'monotoneCubic', lineCap: 'round', dashArray: [0, 0, 4] },
    fill: { type: ['solid', 'solid', 'solid'], opacity: [0.1, 1, 1] },
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
        forceNiceScale: true,
        decimalsInFloat: 0,
        labels: { formatter: (v: number) => Math.round(v).toString() },
    },
    tooltip: { ...base.tooltip, shared: true, intersect: false, x: { format: 'ddd d MMM' } },
}));
</script>

<template>
    <VueApexCharts type="line" height="280" :options="options" :series="series" />
</template>
