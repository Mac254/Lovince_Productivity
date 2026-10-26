<script setup lang="ts">
import type { ApexOptions } from 'apexcharts';
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { useChartTheme } from './useChartTheme';

/** Completed in the range vs. open now, per category, as horizontal bars from one baseline. */
const props = defineProps<{ rows: { label: string; completed: number; open: number }[] }>();
const { colors, base } = useChartTheme();

const series = computed(() => [
    { name: 'Completed in range', data: props.rows.map((r) => r.completed) },
    { name: 'Open now', data: props.rows.map((r) => r.open) },
]);

const options = computed<ApexOptions>(() => ({
    ...base,
    chart: { ...base.chart, stacked: false },
    colors: [colors.series1, colors.series2],
    plotOptions: { bar: { horizontal: true, barHeight: '70%', borderRadius: 4, borderRadiusApplication: 'end' } },
    stroke: { show: true, width: 2, colors: [colors.surface] },
    xaxis: {
        categories: props.rows.map((r) => r.label),
        labels: { formatter: (v: string) => Math.round(Number(v)).toString() },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: { labels: { minWidth: 96, maxWidth: 120, style: { colors: colors.text, fontWeight: 600 } } },
    grid: { ...base.grid, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
    tooltip: { ...base.tooltip, shared: true, intersect: false },
}));
</script>

<template>
    <VueApexCharts type="bar" :height="64 + rows.length * 48" :options="options" :series="series" />
</template>
