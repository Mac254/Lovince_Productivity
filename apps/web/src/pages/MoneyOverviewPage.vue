<script setup lang="ts">
import { api } from '@focus/api-client';
import type { Currency, Transaction } from '@focus/types';
import { todayIso } from '@/lib/dates';
import { useAccountingLists, useTransactionActions } from '@/features/accounting/useAccounting';
import { currencyOptions, formatMoney, monthOf, type Period } from '@/features/accounting/money';

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
                <Button variant="outline" icon-start="report" :to="{ name: 'financial-reports' }">Reports</Button>
                <Button
                    icon-start="add"
                    :disabled="!lists.accounts.value.length"
                    @click="record(null)"
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
                    hint="Money in less money out, transfers left out"
                />
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <section class="flex flex-col gap-4 xl:col-span-2" aria-labelledby="recent-heading">
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
                    <EmptyState v-else icon="transfer" title="Nothing recorded yet" message="Record income, spending or a transfer to see it here." />
                </section>
                <section class="flex flex-col gap-4" aria-labelledby="insights-heading">
                    <SectionHeader title="This period" title-id="insights-heading" />
                    <MoneyInsightList
                        v-if="data.insights.length"
                        :insights="data.insights"
                        :currency="data.currency"
                        :monthly="data.previous.monthly"
                    />
                    <Text v-else tone="muted">Insights appear once there are a few transactions to compare.</Text>
                    <Card v-if="data.top_expenses.length" class="flex flex-col gap-3">
                        <CardHeader title="Top spending" title-id="top-spending-heading" icon="tag" :level="3" class="mb-0" />
                        <ul class="flex flex-col gap-2">
                            <li v-for="row in data.top_expenses" :key="row.id ?? 'none'" class="flex items-baseline justify-between gap-3">
                                <span>{{ row.name }}</span>
                                <span class="font-semibold tabular">{{ money(row.amount) }}</span>
                            </li>
                        </ul>
                    </Card>
                </section>
            </div>
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
