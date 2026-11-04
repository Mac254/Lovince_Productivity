<script setup lang="ts">
import { api } from '@focus/api-client';
import type { CategoryAmount, Currency } from '@focus/types';
import { todayIso } from '@/lib/dates';
import { currencyOptions, formatMoney, monthOf, type Period } from '@/features/accounting/money';

const period = ref<Period>(monthOf(todayIso()));
const currency = ref<Currency | ''>('');
const { data, loading, error, run } = useAsync(async (signal) => {
    const query = { ...period.value, currency: currency.value || undefined };
    const [statement, cash, accounts] = await Promise.all([
        api.accounting.reports.incomeStatement(query, { signal }),
        api.accounting.reports.cashFlow(query, { signal }),
        api.accounting.accounts.list({ archived: true }, { signal }),
    ]);
    return { statement, cash, currencies: [...new Set(accounts.map((a) => a.currency))].sort() };
});

const money = (minor: number) => (data.value ? formatMoney(minor, data.value.statement.currency) : '—');
const options = computed(() => currencyOptions(data.value?.currencies ?? []));
const share = (row: CategoryAmount, total: number) => (total > 0 ? Math.max(0, Math.round((row.amount / total) * 100)) : 0);

watch([period, currency], run);
onMounted(run);
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader
            title="Money reports"
            title-id="page-title"
            description="Income statement and cash flow for the month. Transfers between your accounts are left out of the totals."
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
        <div v-else-if="data" class="grid gap-6 xl:grid-cols-2">
            <Card class="flex flex-col gap-6">
                <CardHeader title="Income statement" title-id="statement-heading" icon="report" class="mb-0" />
                <section v-for="side in (['income', 'expenses'] as const)" :key="side" class="flex flex-col gap-3">
                    <div class="flex items-baseline justify-between gap-3">
                        <Heading :level="3" role="lead">{{ side === 'income' ? 'Income' : 'Spending' }}</Heading>
                        <span class="font-semibold tabular">{{ money(data.statement[side].total) }}</span>
                    </div>
                    <ul v-if="data.statement[side].categories.length" class="flex flex-col gap-3">
                        <li v-for="row in data.statement[side].categories" :key="row.id ?? 'none'" class="flex flex-col gap-1">
                            <div class="flex items-baseline justify-between gap-3 text-label">
                                <span>{{ row.name }}</span>
                                <span class="tabular">{{ money(row.amount) }}</span>
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
                    Balance adjustments of {{ formatMoney(data.cash.adjustments, data.statement.currency, { signed: true }) }} are included in the
                    closing balance but not in money in or out.
                </Text>
                <Table v-if="data.cash.accounts.length" caption="Cash flow by account" label="Cash flow by account">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-full">Account</TableHead>
                            <TableHead align="end">In</TableHead>
                            <TableHead align="end">Out</TableHead>
                            <TableHead align="end">Transfers</TableHead>
                            <TableHead v-if="data.cash.adjustments" align="end">Adjusted</TableHead>
                            <TableHead align="end">Closing</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in data.cash.accounts" :key="row.id">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell numeric align="end">{{ money(row.inflows) }}</TableCell>
                            <TableCell numeric align="end">{{ money(row.outflows) }}</TableCell>
                            <TableCell numeric align="end" class="text-fg-muted">{{
                                formatMoney(row.transfers_in - row.transfers_out, data.statement.currency, { signed: true })
                            }}</TableCell>
                            <TableCell v-if="data.cash.adjustments" numeric align="end" class="text-fg-muted">{{
                                formatMoney(row.adjustments, data.statement.currency, { signed: true })
                            }}</TableCell>
                            <TableCell numeric align="end" class="font-semibold">{{ money(row.closing) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <Text v-else tone="muted">Add an account to see its cash flow.</Text>
            </Card>
        </div>
    </div>
</template>
