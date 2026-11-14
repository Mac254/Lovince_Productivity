<script setup lang="ts">
import { api } from '@focus/api-client';
import type { CategoryAmount, Currency } from '@focus/types';
import { todayIso } from '@/lib/dates';
import { currencyOptions, formatMoney, monthLabel, monthOf, type Period } from '@/features/accounting/money';

const period = ref<Period>(monthOf(todayIso()));
const currency = ref<Currency | ''>('');
const { data, loading, error, run } = useAsync(async (signal) => {
    const query = { ...period.value, currency: currency.value || undefined };
    const year = { from: monthOf(period.value.from, -11).from, to: period.value.to, currency: query.currency };
    const [statement, previous, cash, trend, accounts] = await Promise.all([
        api.accounting.reports.incomeStatement(query, { signal }),
        api.accounting.reports.incomeStatement(
            { ...monthOf(period.value.from, -1), currency: query.currency },
            { signal },
        ),
        api.accounting.reports.cashFlow(query, { signal }),
        api.accounting.reports.trend(year, { signal }),
        api.accounting.accounts.list({ archived: true }, { signal }),
    ]);
    return { statement, previous, cash, trend, currencies: [...new Set(accounts.map((a) => a.currency))].sort() };
});

const movedNote = (row: { transfers_in: number; transfers_out: number; adjustments: number }) => {
    if (!data.value) return '';
    const currency = data.value.statement.currency;
    const transfers = row.transfers_in - row.transfers_out;
    return [
        transfers ? `Transfers ${formatMoney(transfers, currency, { signed: true })}` : '',
        row.adjustments ? `Adjusted ${formatMoney(row.adjustments, currency, { signed: true })}` : '',
    ]
        .filter(Boolean)
        .join(' · ');
};
const money = (minor: number) => (data.value ? formatMoney(minor, data.value.statement.currency) : '—');
const options = computed(() => currencyOptions(data.value?.currencies ?? []));
const share = (row: CategoryAmount, total: number) =>
    total > 0 ? Math.max(0, Math.round((row.amount / total) * 100)) : 0;
const rate = (income: number, net: number) => (income > 0 ? Math.round((net / income) * 100) : null);
const percentLabel = (value: number | null) => (value === null ? '—' : `${value}%`);

/** The month-on-month change as a short line, `better` saying whether a rise is good news. */
function change(current: number, before: number, better: 'up' | 'down'): { text: string; tone: string } | undefined {
    if (!data.value || (!current && !before)) return undefined;
    if (!before) return { text: 'Nothing last month', tone: 'text-fg-muted' };
    const percent = Math.round(((current - before) / Math.abs(before)) * 100);
    if (percent === 0) return { text: 'Same as last month', tone: 'text-fg-muted' };
    const good = percent > 0 === (better === 'up');
    return {
        text: `${percent > 0 ? '+' : '−'}${Math.abs(percent)}% on last month`,
        tone: good ? 'text-success-fg' : 'text-danger-fg',
    };
}

const figures = computed(() => {
    if (!data.value) return [];
    const { statement: now, previous: before } = data.value;
    return [
        {
            label: 'Income',
            value: money(now.income.total),
            change: change(now.income.total, before.income.total, 'up'),
        },
        {
            label: 'Spending',
            value: money(now.expenses.total),
            change: change(now.expenses.total, before.expenses.total, 'down'),
        },
        {
            label: 'Net income',
            value: formatMoney(now.net, now.currency, { signed: true }),
            change: change(now.net, before.net, 'up'),
        },
        {
            label: 'Savings rate',
            value: percentLabel(rate(now.income.total, now.net)),
            change:
                rate(before.income.total, before.net) === null
                    ? undefined
                    : {
                          text: `${percentLabel(rate(before.income.total, before.net))} last month`,
                          tone: 'text-fg-muted',
                      },
        },
    ];
});

/** Last month's amount for the same category, to compare line by line. */
function before(side: 'income' | 'expenses', row: CategoryAmount): number {
    return data.value?.previous[side].categories.find((c) => c.id === row.id)?.amount ?? 0;
}

const yearTotals = computed(() => {
    const months = data.value?.trend.months ?? [];
    const income = months.reduce((sum, m) => sum + m.income, 0);
    const expenses = months.reduce((sum, m) => sum + m.expenses, 0);
    return { income, expenses, net: income - expenses };
});

watch([period, currency], run);
onMounted(run);
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader
            title="Statements"
            title-id="page-title"
            description="Income statement, cash flow and the year so far. Transfers between your accounts are left out of the totals."
        />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <PeriodPicker v-model="period" />
            <Select
                v-if="options.length > 1"
                id="report-currency"
                v-model="currency"
                label="Currency"
                :options="options"
                class="w-40"
            />
        </div>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="loading && !data" class="grid gap-6 xl:grid-cols-2" aria-hidden="true">
            <Skeleton v-for="n in 2" :key="n" class="h-80" />
        </div>
        <template v-else-if="data">
            <dl class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <Card v-for="figure in figures" :key="figure.label" as="div" class="gap-1">
                    <dt class="text-label font-semibold text-fg-muted">{{ figure.label }}</dt>
                    <dd class="font-display text-title font-semibold tracking-tight tabular">{{ figure.value }}</dd>
                    <dd v-if="figure.change" class="text-label" :class="figure.change.tone">
                        {{ figure.change.text }}
                    </dd>
                </Card>
            </dl>
            <div class="grid gap-6 xl:grid-cols-2">
                <Card class="flex flex-col gap-6">
                    <CardHeader title="Income statement" title-id="statement-heading" icon="report" class="mb-0" />
                    <section v-for="side in ['income', 'expenses'] as const" :key="side" class="flex flex-col gap-3">
                        <div class="flex items-baseline justify-between gap-3">
                            <Heading :level="3" role="lead">{{ side === 'income' ? 'Income' : 'Spending' }}</Heading>
                            <span class="font-semibold tabular">{{ money(data.statement[side].total) }}</span>
                        </div>
                        <ul v-if="data.statement[side].categories.length" class="flex flex-col gap-3">
                            <li
                                v-for="row in data.statement[side].categories"
                                :key="row.id ?? 'none'"
                                class="flex flex-col gap-1"
                            >
                                <div class="flex items-baseline justify-between gap-3 text-label">
                                    <span class="min-w-0 truncate">{{ row.name }}</span>
                                    <span class="flex items-baseline gap-3">
                                        <span class="hidden text-caption text-fg-muted tabular sm:inline">{{
                                            before(side, row)
                                                ? `${money(before(side, row))} last month`
                                                : 'new this month'
                                        }}</span>
                                        <span class="font-semibold tabular">{{ money(row.amount) }}</span>
                                    </span>
                                </div>
                                <ProgressBar
                                    :value="share(row, data.statement[side].total)"
                                    :label="`${row.name}, ${share(row, data.statement[side].total)}% of ${side === 'income' ? 'income' : 'spending'}`"
                                    :tone="side === 'income' ? 'highlight' : 'accent'"
                                />
                            </li>
                        </ul>
                        <Text v-else tone="muted">Nothing recorded this period.</Text>
                    </section>
                    <div class="flex items-baseline justify-between gap-3 border-t border-border pt-4">
                        <span class="font-semibold">Net income</span>
                        <span class="font-display text-title font-semibold tabular">{{
                            formatMoney(data.statement.net, data.statement.currency, { signed: true })
                        }}</span>
                    </div>
                </Card>

                <Card class="flex flex-col gap-6">
                    <CardHeader title="Cash flow" title-id="cash-heading" icon="transfer" class="mb-0" />
                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div>
                            <dt class="text-label text-fg-muted">Opening</dt>
                            <dd class="font-semibold tabular">{{ money(data.cash.opening) }}</dd>
                        </div>
                        <div>
                            <dt class="text-label text-fg-muted">Money in</dt>
                            <dd class="font-semibold tabular text-success-fg">{{ money(data.cash.inflows) }}</dd>
                        </div>
                        <div>
                            <dt class="text-label text-fg-muted">Money out</dt>
                            <dd class="font-semibold tabular">{{ money(data.cash.outflows) }}</dd>
                        </div>
                        <div>
                            <dt class="text-label text-fg-muted">Closing</dt>
                            <dd class="font-semibold tabular">{{ money(data.cash.closing) }}</dd>
                        </div>
                    </dl>
                    <Text v-if="data.cash.adjustments" as="p" tone="muted">
                        Balance adjustments of
                        {{ formatMoney(data.cash.adjustments, data.statement.currency, { signed: true }) }} are included
                        in the closing balance but not in money in or out.
                    </Text>
                    <Table v-if="data.cash.accounts.length" caption="Cash flow by account" label="Cash flow by account">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-full">Account</TableHead>
                                <TableHead align="end">In</TableHead>
                                <TableHead align="end">Out</TableHead>
                                <TableHead align="end" class="hidden 2xl:table-cell">Transfers</TableHead>
                                <TableHead v-if="data.cash.adjustments" align="end" class="hidden 2xl:table-cell"
                                    >Adjusted</TableHead
                                >
                                <TableHead align="end">Closing</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in data.cash.accounts" :key="row.id">
                                <TableCell class="font-medium">
                                    {{ row.name }}
                                    <span
                                        v-if="movedNote(row)"
                                        class="block text-caption font-normal text-fg-muted tabular 2xl:hidden"
                                        >{{ movedNote(row) }}</span
                                    >
                                </TableCell>
                                <TableCell numeric align="end">{{ money(row.inflows) }}</TableCell>
                                <TableCell numeric align="end">{{ money(row.outflows) }}</TableCell>
                                <TableCell numeric align="end" class="hidden text-fg-muted 2xl:table-cell">{{
                                    formatMoney(row.transfers_in - row.transfers_out, data.statement.currency, {
                                        signed: true,
                                    })
                                }}</TableCell>
                                <TableCell
                                    v-if="data.cash.adjustments"
                                    numeric
                                    align="end"
                                    class="hidden text-fg-muted 2xl:table-cell"
                                    >{{
                                        formatMoney(row.adjustments, data.statement.currency, { signed: true })
                                    }}</TableCell
                                >
                                <TableCell numeric align="end" class="font-semibold">{{
                                    money(row.closing)
                                }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <Text v-else tone="muted">Add an account to see its cash flow.</Text>
                </Card>
            </div>

            <Card as="section" aria-labelledby="year-heading" class="flex flex-col gap-4">
                <CardHeader title="Twelve months" title-id="year-heading" icon="metrics" class="mb-0" />
                <MoneyTrendChart :months="data.trend.months" :currency="data.statement.currency" />
                <Table caption="Income, spending and net income by month" label="Twelve months">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-full">Month</TableHead>
                            <TableHead align="end">Income</TableHead>
                            <TableHead align="end">Spending</TableHead>
                            <TableHead align="end">Net</TableHead>
                            <TableHead align="end" class="hidden sm:table-cell">Saved</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in [...data.trend.months].reverse()" :key="row.month">
                            <TableCell class="font-medium whitespace-nowrap">{{
                                monthLabel({ from: row.from, to: row.to })
                            }}</TableCell>
                            <TableCell numeric align="end">{{ money(row.income) }}</TableCell>
                            <TableCell numeric align="end">{{ money(row.expenses) }}</TableCell>
                            <TableCell
                                numeric
                                align="end"
                                class="font-semibold"
                                :class="row.net < 0 && 'text-danger-fg'"
                                >{{ formatMoney(row.net, data.statement.currency, { signed: true }) }}</TableCell
                            >
                            <TableCell numeric align="end" class="hidden text-fg-muted sm:table-cell">{{
                                percentLabel(rate(row.income, row.net))
                            }}</TableCell>
                        </TableRow>
                        <TableRow class="font-semibold">
                            <TableCell>Total</TableCell>
                            <TableCell numeric align="end">{{ money(yearTotals.income) }}</TableCell>
                            <TableCell numeric align="end">{{ money(yearTotals.expenses) }}</TableCell>
                            <TableCell numeric align="end">{{
                                formatMoney(yearTotals.net, data.statement.currency, { signed: true })
                            }}</TableCell>
                            <TableCell numeric align="end" class="hidden sm:table-cell">{{
                                percentLabel(rate(yearTotals.income, yearTotals.net))
                            }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </Card>
        </template>
    </div>
</template>
