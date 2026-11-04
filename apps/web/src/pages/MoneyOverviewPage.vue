<script setup lang="ts">
import { api } from '@focus/api-client';
import type { Currency, Transaction } from '@focus/types';
import { todayIso } from '@/lib/dates';
import { useAccountingLists, useTransactionActions } from '@/features/accounting/useAccounting';
import { daysUntil, formatDate } from '@/lib/dates';
import { currencyOptions, formatMoney, frequencyLabels, monthOf, type Period } from '@/features/accounting/money';

const period = ref<Period>(monthOf(todayIso()));
const currency = ref<Currency | ''>('');
const { data, loading, error, run } = useAsync((signal) =>
    api.accounting.dashboard({ ...period.value, currency: currency.value || undefined }, { signal }),
);
const lists = useAccountingLists();
const actions = useTransactionActions(refresh);

const formOpen = ref(false);
const editing = ref<Transaction | null>(null);
const accountOpen = ref(false);

const money = (minor: number) => (data.value ? formatMoney(minor, data.value.currency) : '—');
const options = computed(() => currencyOptions(data.value?.currencies ?? []));
const change = (current: number, previous: number) => {
    if (!previous) return undefined;
    const diff = current - previous;
    return `${diff >= 0 ? '+' : '−'}${formatMoney(Math.abs(diff), data.value!.currency)} vs ${data.value!.previous.monthly ? 'last month' : 'previous period'}`;
};

const savingsRate = computed(() =>
    data.value && data.value.income > 0 ? Math.round((data.value.net_cash_flow / data.value.income) * 100) : null,
);
const percentOf = (part: number, whole: number) => (whole > 0 ? Math.round((part / whole) * 100) : 0);
const due = (date: string) => {
    const days = daysUntil(date);
    return days <= 0 ? 'Today' : days === 1 ? 'Tomorrow' : formatDate(date);
};

async function refresh(): Promise<void> {
    await Promise.all([run(), lists.load()]);
}

function record(transaction: Transaction | null): void {
    editing.value = transaction;
    formOpen.value = true;
}

watch([period, currency], run);
onMounted(refresh);
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader title="Money" title-id="page-title" description="Your accounts, income and spending for the month.">
            <template #actions>
                <Button variant="outline" icon-start="calendar-clock" :to="{ name: 'recurring' }">Recurring</Button>
                <Button icon-start="add" :disabled="!lists.accounts.value.length" @click="record(null)"
                    >New transaction</Button
                >
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <PeriodPicker v-model="period" />
            <Select
                v-if="options.length > 1"
                id="money-currency"
                v-model="currency"
                label="Currency"
                :options="options"
                class="w-40"
            />
        </div>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="loading && !data" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-hidden="true">
            <Skeleton v-for="n in 4" :key="n" class="h-36" />
        </div>
        <EmptyState
            v-else-if="data && !lists.accounts.value.length"
            icon="wallet"
            title="Add your first account"
            message="Start with where your money is: cash, a bank, M-Pesa or a SACCO. Balances are worked out from what you record."
        >
            <Button icon-start="add" @click="accountOpen = true">Add an account</Button>
        </EmptyState>
        <template v-else-if="data">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MoneyTile
                    label="Available"
                    :value="money(data.available_balance)"
                    icon="wallet"
                    emphasis
                    :hint="
                        data.available_balance !== data.balance
                            ? `${money(data.balance)} before pending payments`
                            : `Across ${data.accounts_count} ${data.accounts_count === 1 ? 'account' : 'accounts'}`
                    "
                />
                <MoneyTile
                    label="Income"
                    :value="money(data.income)"
                    icon="arrow-up-right"
                    motion="lift"
                    :hint="change(data.income, data.previous_totals.income)"
                />
                <MoneyTile
                    label="Spending"
                    :value="money(data.expenses)"
                    icon="tag"
                    motion="wiggle"
                    :hint="change(data.expenses, data.previous_totals.expenses)"
                />
                <MoneyTile
                    label="Net cash flow"
                    :value="formatMoney(data.net_cash_flow, data.currency, { signed: true })"
                    icon="transfer"
                    motion="nudge"
                    :hint="
                        savingsRate === null
                            ? 'Money in less money out, transfers left out'
                            : savingsRate >= 0
                              ? `You kept ${savingsRate}% of your income`
                              : `Spent ${-savingsRate}% more than came in`
                    "
                />
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <Card as="section" aria-labelledby="trend-heading" class="flex flex-col gap-2 xl:col-span-2">
                    <CardHeader title="Six months" title-id="trend-heading" icon="metrics" class="mb-0">
                        <Button variant="ghost" size="sm" icon-end="arrow-right" :to="{ name: 'financial-reports' }"
                            >Statements</Button
                        >
                    </CardHeader>
                    <MoneyTrendChart :months="data.trend" :currency="data.currency" />
                </Card>

                <Card as="section" aria-labelledby="budgets-heading" class="flex flex-col gap-4">
                    <CardHeader title="Budgets" title-id="budgets-heading" icon="target" class="mb-0">
                        <Button variant="ghost" size="sm" icon-end="arrow-right" :to="{ name: 'budgets' }">{{
                            data.budgets.items.length ? 'All' : 'Set one'
                        }}</Button>
                    </CardHeader>
                    <template v-if="data.budgets.items.length">
                        <div>
                            <p class="font-display text-title font-semibold tabular">
                                {{ money(Math.abs(data.budgets.amount - data.budgets.spent)) }}
                            </p>
                            <p class="text-label text-fg-muted">
                                {{ data.budgets.amount >= data.budgets.spent ? 'left' : 'over' }} of
                                {{ money(data.budgets.amount) }} budgeted
                            </p>
                        </div>
                        <ul class="flex flex-col gap-3">
                            <li v-for="row in data.budgets.items" :key="row.id" class="flex flex-col gap-1">
                                <div class="flex items-baseline justify-between gap-3 text-label">
                                    <span class="truncate">{{ row.category }}</span>
                                    <span
                                        class="tabular"
                                        :class="
                                            row.spent > row.amount ? 'font-semibold text-danger-fg' : 'text-fg-muted'
                                        "
                                        >{{ percentOf(row.spent, row.amount) }}%</span
                                    >
                                </div>
                                <ProgressBar
                                    :value="percentOf(row.spent, row.amount)"
                                    :tone="row.spent > row.amount ? 'danger' : 'accent'"
                                    :label="`${row.category}: ${percentOf(row.spent, row.amount)}% of its budget`"
                                />
                            </li>
                        </ul>
                    </template>
                    <Text v-else tone="muted"
                        >Give a category such as food or transport a monthly limit to see how you are tracking.</Text
                    >
                </Card>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <Card as="section" aria-labelledby="insights-heading" class="flex flex-col gap-3">
                    <CardHeader title="This period" title-id="insights-heading" icon="lightbulb" class="mb-0" />
                    <MoneyInsightList
                        v-if="data.insights.length"
                        :insights="data.insights"
                        :currency="data.currency"
                        :monthly="data.previous.monthly"
                    />
                    <Text v-else tone="muted">Insights appear once there are a few transactions to compare.</Text>
                </Card>
                <Card as="section" aria-labelledby="upcoming-heading" class="flex flex-col gap-3">
                    <CardHeader title="Coming up" title-id="upcoming-heading" icon="calendar-clock" class="mb-0">
                        <Button variant="ghost" size="sm" icon-end="arrow-right" :to="{ name: 'recurring' }"
                            >All</Button
                        >
                    </CardHeader>
                    <ul v-if="data.upcoming.length" class="flex flex-col gap-3">
                        <li
                            v-for="rule in data.upcoming"
                            :key="rule.id"
                            class="flex items-baseline justify-between gap-3"
                        >
                            <span class="min-w-0">
                                <span class="block truncate">{{ rule.description }}</span>
                                <span class="text-caption text-fg-muted"
                                    >{{ due(rule.next_date!) }} · {{ frequencyLabels[rule.frequency] }}</span
                                >
                            </span>
                            <span
                                class="font-semibold whitespace-nowrap tabular"
                                :class="rule.direction === 'in' && rule.type !== 'transfer' && 'text-success-fg'"
                                >{{
                                    formatMoney(
                                        rule.direction === 'out' && rule.type !== 'transfer'
                                            ? -rule.amount
                                            : rule.amount,
                                        rule.currency,
                                        {
                                            signed: rule.type !== 'transfer',
                                        },
                                    )
                                }}</span
                            >
                        </li>
                    </ul>
                    <Text v-else tone="muted">Nothing repeats in the next two weeks.</Text>
                </Card>
                <Card as="section" aria-labelledby="top-spending-heading" class="flex flex-col gap-3">
                    <CardHeader title="Top spending" title-id="top-spending-heading" icon="tag" class="mb-0" />
                    <ul v-if="data.top_expenses.length" class="flex flex-col gap-3">
                        <li v-for="row in data.top_expenses" :key="row.id ?? 'none'" class="flex flex-col gap-1">
                            <div class="flex items-baseline justify-between gap-3 text-label">
                                <span class="truncate">{{ row.name }}</span>
                                <span class="font-semibold tabular">{{ money(row.amount) }}</span>
                            </div>
                            <ProgressBar
                                :value="percentOf(row.amount, data.expenses)"
                                :label="`${row.name}, ${percentOf(row.amount, data.expenses)}% of spending`"
                            />
                        </li>
                    </ul>
                    <Text v-else tone="muted">No spending recorded this period.</Text>
                </Card>
            </div>

            <section class="flex flex-col gap-4" aria-labelledby="recent-heading">
                <SectionHeader title="Recent transactions" title-id="recent-heading">
                    <Button variant="ghost" size="sm" icon-end="arrow-right" :to="{ name: 'transactions' }"
                        >All transactions</Button
                    >
                </SectionHeader>
                <TransactionTable
                    v-if="data.recent.length"
                    :transactions="data.recent"
                    @edit="record"
                    @void="actions.voiding.value = $event"
                    @reverse="actions.reversing.value = $event"
                />
                <EmptyState
                    v-else
                    icon="transfer"
                    title="Nothing recorded yet"
                    message="Record income, spending or a transfer to see it here."
                />
            </section>
        </template>

        <TransactionFormDialog
            v-model:open="formOpen"
            :transaction="editing"
            :accounts="lists.accounts.value"
            :categories="lists.categories.value"
            @saved="refresh"
        />
        <AccountFormDialog v-model:open="accountOpen" @saved="refresh" />
        <TransactionConfirms
            v-model:voiding="actions.voiding.value"
            v-model:reversing="actions.reversing.value"
            :busy="actions.busy.value"
            @void="actions.confirmVoid"
            @reverse="actions.confirmReverse"
        />
    </div>
</template>
