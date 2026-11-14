<script setup lang="ts">
import type { AccountingDashboard, Currency } from '@focus/types';
import type { ApexOptions } from 'apexcharts';
import { VueApexCharts } from '@/features/metrics/apexChart';
import { useChartTheme } from '@/features/metrics/useChartTheme';
import { decimalsOf, formatMoney } from './money';

/** What was spent each day, as a smooth curve with a light wash under it. */
const props = withDefaults(
    defineProps<{ days: AccountingDashboard['daily_spending']; currency: Currency; height?: number }>(),
    { height: 220 },
);
const { colors, base } = useChartTheme();

const major = (minor: number) => minor / 10 ** decimalsOf(props.currency);
const compact = new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 });

const series = computed(() => [{ name: 'Spending', data: props.days.map((d) => ({ x: d.date, y: major(d.amount) })) }]);

const options = computed<ApexOptions>(() => ({
    ...base,
    chart: { ...base.chart, id: 'daily-spending' },
    colors: [colors.series1],
    stroke: { width: 2, curve: 'monotoneCubic', lineCap: 'round' },
    fill: { type: 'solid', opacity: 0.1 },
    markers: { size: 0, strokeColors: colors.surface, strokeWidth: 2, hover: { size: 5 } },
    xaxis: {
        type: 'datetime',
        labels: { datetimeUTC: false, format: 'd MMM' },
        axisBorder: { show: false },
        axisTicks: { show: false },
        tooltip: { enabled: false },
    },
    yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (v: number) => compact.format(v) } },
    tooltip: {
        ...base.tooltip,
        intersect: false,
        x: { format: 'ddd d MMM' },
        y: { formatter: (v: number) => formatMoney(Math.round(v * 10 ** decimalsOf(props.currency)), props.currency) },
    },
}));
</script>

<template>
    <VueApexCharts type="area" :height="height" :options="options" :series="series" />
</template>
