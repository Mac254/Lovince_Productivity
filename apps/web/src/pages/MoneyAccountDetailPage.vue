<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { FinancialAccount, FinancialGoal, Transaction } from '@focus/types';
import { useAccountingLists, useTransactionActions } from '@/features/accounting/useAccounting';
import { accountTypeIcons, accountTypeLabels, formatMoney, goalKindIcons } from '@/features/accounting/money';
import { formatDate } from '@/lib/dates';

const route = useRoute();
const toast = useToast();
const id = computed(() => Number(route.params.id));

const account = ref<FinancialAccount | null>(null);
const goals = ref<FinancialGoal[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const page = ref(1);
const lists = useAccountingLists();

const transactions = useAsync((signal) =>
    api.accounting.transactions.list({ account_id: id.value, page: page.value }, { signal }),
);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const [loaded, allGoals] = await Promise.all([
            api.accounting.accounts.get(id.value),
            api.accounting.goals.list(),
            lists.load(),
            transactions.run(),
        ]);
        account.value = loaded;
        goals.value = allGoals.filter((goal) => goal.account?.id === loaded.id);
    } catch (e) {
        error.value = errorMessage(e, 'Could not load this account.');
    } finally {
        loading.value = false;
    }
}

const actions = useTransactionActions(load);
const editOpen = ref(false);
const formOpen = ref(false);
const editing = ref<Transaction | null>(null);

function record(transaction: Transaction | null): void {
    editing.value = transaction;
    formOpen.value = true;
}

async function setArchived(archived: boolean): Promise<void> {
    if (!account.value) return;
    try {
        account.value = await api.accounting.accounts.update(account.value.id, { archived });
        toast.success(archived ? 'Account archived' : 'Account restored');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not update the account.'));
    }
}

const money = (minor: number) => formatMoney(minor, account.value?.currency ?? 'KES');
const goalProgress = (goal: FinancialGoal) =>
    goal.target_amount > 0 ? Math.min(100, Math.round((goal.saved / goal.target_amount) * 100)) : 0;

watch(page, () => void transactions.run());
watch(
    id,
    () => {
        page.value = 1;
        void load();
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader
            :title="account?.name ?? 'Account'"
            title-id="page-title"
            :back="{ to: { name: 'financial-accounts' }, label: 'Back to all accounts' }"
        >
            <template v-if="account" #eyebrow>
                <Badge tone="inverse" :icon="accountTypeIcons[account.type]">{{
                    accountTypeLabels[account.type]
                }}</Badge>
                <Badge tone="neutral">{{ account.currency }}</Badge>
                <Badge v-if="account.archived" tone="warning">Archived</Badge>
            </template>
            <template v-if="account" #actions>
                <Button variant="outline" icon-start="edit" @click="editOpen = true">Edit</Button>
                <Button
                    variant="outline"
                    :icon-start="account.archived ? 'undo' : 'lock'"
                    @click="setArchived(!account.archived)"
                    >{{ account.archived ? 'Restore' : 'Archive' }}</Button
                >
                <Button v-if="!account.archived" icon-start="add" @click="record(null)">New transaction</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="load" />
        <div v-else-if="loading && !account" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-busy="true">
            <Skeleton v-for="n in 4" :key="n" class="h-36" />
        </div>

        <template v-else-if="account">
            <section aria-label="Account in numbers" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MoneyTile
                    label="Balance"
                    :value="money(account.balance)"
                    :icon="accountTypeIcons[account.type]"
                    emphasis
                    :hint="
                        account.available_balance !== account.balance
                            ? `${money(account.available_balance)} available after pending payments`
                            : 'Opening balance plus everything recorded'
                    "
                />
                <MoneyTile
                    label="Opening balance"
                    :value="money(account.opening_balance)"
                    icon="wallet"
                    :hint="`Since ${formatDate(account.created_at, { day: 'numeric', month: 'short', year: 'numeric' })}`"
                />
                <MoneyTile
                    label="Money in"
                    :value="money(account.total_inflows)"
                    icon="arrow-up-right"
                    motion="lift"
                    hint="Income and transfers in"
                />
                <MoneyTile
                    label="Money out"
                    :value="money(account.total_outflows)"
                    icon="transfer"
                    motion="nudge"
                    hint="Spending and transfers out"
                />
            </section>

            <section v-if="goals.length" aria-labelledby="account-goals" class="flex flex-col gap-4">
                <h2 id="account-goals" class="font-display text-title font-semibold tracking-tight">Goals it funds</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Card v-for="goal in goals" :key="goal.id" as="div" class="gap-3">
                        <div class="flex items-center justify-between gap-3">
                            <RouterLink
                                :to="{ name: 'money-goals' }"
                                class="flex min-w-0 items-center gap-2 font-semibold underline-offset-4 hover:underline"
                            >
                                <Icon :name="goalKindIcons[goal.kind]" />
                                <span class="truncate">{{ goal.name }}</span>
                            </RouterLink>
                            <Badge v-if="goal.achieved" tone="success" icon="check-circle">Reached</Badge>
                        </div>
                        <ProgressBar
                            :value="goalProgress(goal)"
                            :label="`${goal.name} progress`"
                            :tone="goal.achieved ? 'highlight' : 'accent'"
                        />
                        <Text role="label" tone="muted" tabular>
                            {{ money(goal.saved) }} of {{ money(goal.target_amount) }}
                            <template v-if="goal.monthly_needed"> · {{ money(goal.monthly_needed) }} a month</template>
                            <template v-if="goal.target_date">
                                · by {{ formatDate(goal.target_date, { month: 'short', year: 'numeric' }) }}</template
                            >
                        </Text>
                    </Card>
                </div>
            </section>

            <section aria-labelledby="account-transactions" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="account-transactions" class="font-display text-title font-semibold tracking-tight">
                        Transactions
                    </h2>
                    <Text v-if="transactions.data.value" role="label" tone="muted" tabular>
                        {{ transactions.data.value.meta.total }}
                        {{ transactions.data.value.meta.total === 1 ? 'transaction' : 'transactions' }}
                    </Text>
                </div>
                <ErrorState
                    v-if="transactions.error.value"
                    :message="transactions.error.value"
                    @retry="transactions.run()"
                />
                <template v-else-if="transactions.data.value?.data.length">
                    <TransactionTable
                        :transactions="transactions.data.value.data"
                        :account-id="account.id"
                        @edit="record"
                        @void="actions.voiding.value = $event"
                        @reverse="actions.reversing.value = $event"
                    />
                    <Pagination v-model:page="page" :last-page="transactions.data.value.meta.last_page" />
                </template>
                <EmptyState
                    v-else
                    icon="transfer"
                    title="No transactions yet"
                    message="Money in, money out and transfers for this account will show up here."
                >
                    <Button v-if="!account.archived" icon-start="add" @click="record(null)">Record one</Button>
                </EmptyState>
            </section>
        </template>

        <AccountFormDialog v-model:open="editOpen" :account="account" @saved="load" />
        <TransactionFormDialog
            v-model:open="formOpen"
            :transaction="editing"
            :accounts="lists.accounts.value"
            :categories="lists.categories.value"
            :default-account-id="account?.id ?? null"
            @saved="load"
        />
        <TransactionConfirms
            v-model:voiding="actions.voiding.value"
            v-model:reversing="actions.reversing.value"
            :busy="actions.busy.value"
            @void="actions.confirmVoid"
            @reverse="actions.confirmReverse"
        />
    </div>
</template>
