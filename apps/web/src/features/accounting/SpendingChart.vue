<script setup lang="ts">
import type { AccountingDashboard, Currency } from '@focus/types';
import type { ApexOptions } from 'apexcharts';
import { VueApexCharts } from '@/features/metrics/apexChart';
import { useChartTheme } from '@/features/metrics/useChartTheme';
import { decimalsOf, formatMoney } from './money';

/** What was spent each day, as bars. */
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
    plotOptions: { bar: { columnWidth: '60%', borderRadius: 3, borderRadiusApplication: 'end' } },
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
        x: { format: 'ddd d MMM' },
        y: { formatter: (v: number) => formatMoney(Math.round(v * 10 ** decimalsOf(props.currency)), props.currency) },
    },
}));
</script>

<template>
    <VueApexCharts type="bar" :height="height" :options="options" :series="series" />
</template>
