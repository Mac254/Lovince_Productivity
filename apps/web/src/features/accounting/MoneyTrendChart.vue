<script setup lang="ts">
import type { Currency, MonthTotals } from '@focus/types';
import type { ApexOptions } from 'apexcharts';
import { VueApexCharts } from '@/features/metrics/apexChart';
import { useChartTheme } from '@/features/metrics/useChartTheme';
import { parseDate } from '@/lib/dates';
import { decimalsOf, formatMoney } from './money';

/** Income (green) and spending (grey) as bars for each month, with net income as a line over them. */
const props = withDefaults(defineProps<{ months: MonthTotals[]; currency: Currency; height?: number }>(), {
    height: 280,
});
const { colors, base } = useChartTheme();

const major = (minor: number) => minor / 10 ** decimalsOf(props.currency);
const label = (month: string) =>
    new Intl.DateTimeFormat(undefined, { month: 'short' }).format(parseDate(`${month}-01`));
const compact = new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 });

const series = computed(() => [
    { name: 'Income', type: 'column', data: props.months.map((m) => major(m.income)) },
    { name: 'Spending', type: 'column', data: props.months.map((m) => major(m.expenses)) },
    { name: 'Net income', type: 'line', data: props.months.map((m) => major(m.net)) },
]);

const options = computed<ApexOptions>(() => ({
    ...base,
    chart: { ...base.chart, id: 'money-trend', stacked: false },
    colors: [colors.series1, colors.series2, colors.series3],
    plotOptions: { bar: { columnWidth: '55%', borderRadius: 4, borderRadiusApplication: 'end' } },
    stroke: { width: [0, 0, 2], curve: 'monotoneCubic', lineCap: 'round' },
    markers: { size: [0, 0, 4], strokeColors: colors.surface, strokeWidth: 2, hover: { size: 6 } },
    xaxis: {
        categories: props.months.map((m) => label(m.month)),
        axisBorder: { show: false },
        axisTicks: { show: false },
        tooltip: { enabled: false },
    },
    yaxis: { forceNiceScale: true, labels: { formatter: (v: number) => compact.format(v) } },
    tooltip: {
        ...base.tooltip,
        shared: true,
        intersect: false,
        y: { formatter: (v: number) => formatMoney(Math.round(v * 10 ** decimalsOf(props.currency)), props.currency) },
    },
}));
</script>

<template>
    <VueApexCharts type="line" :height="height" :options="options" :series="series" />
</template>
