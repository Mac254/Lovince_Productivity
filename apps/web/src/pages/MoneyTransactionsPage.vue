<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import { LIMITS, type Transaction, type TransactionFilters, type TransactionStatus, type TransactionType } from '@focus/types';
import { useAccountingLists, useTransactionActions } from '@/features/accounting/useAccounting';
import { transactionStatusLabels, transactionTypeOptions } from '@/features/accounting/money';

const route = useRoute();
const toast = useToast();
const search = ref('');
const type = ref<TransactionType | ''>('');
const status = ref<TransactionStatus | ''>('');
const accountId = ref<number | ''>(Number(route.query.account) || '');
const from = ref('');
const to = ref('');
const page = ref(1);

const filters = computed<TransactionFilters>(() => ({
    search: search.value.trim() || undefined,
    type: type.value || undefined,
    status: status.value || undefined,
    account_id: accountId.value || undefined,
    from: from.value || undefined,
    to: to.value || undefined,
}));
const { data, loading, error, run } = useAsync((signal) =>
    api.accounting.transactions.list({ ...filters.value, page: page.value }, { signal }),
);
const lists = useAccountingLists();
const actions = useTransactionActions(refresh);

const statusOptions = (Object.keys(transactionStatusLabels) as TransactionStatus[]).map((value) => ({
    value,
    label: transactionStatusLabels[value],
}));
const accountOptions = computed(() => lists.accounts.value.map((a) => ({ value: a.id, label: a.name })));
const filtered = computed(() => Object.values(filters.value).some(Boolean));

const formOpen = ref(false);
const editing = ref<Transaction | null>(null);
const exporting = ref(false);

function record(transaction: Transaction | null): void {
    editing.value = transaction;
    formOpen.value = true;
}

async function refresh(): Promise<void> {
    await Promise.all([run(), lists.load()]);
}

async function exportCsv(): Promise<void> {
    exporting.value = true;
    try {
        const blob = await api.accounting.transactions.export(filters.value);
        const url = URL.createObjectURL(blob);
        const link = Object.assign(document.createElement('a'), { href: url, download: 'transactions.csv' });
        link.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        toast.error(errorMessage(e, 'Could not export the transactions.'));
    } finally {
        exporting.value = false;
    }
}

function clear(): void {
    search.value = '';
    type.value = '';
    status.value = '';
    accountId.value = '';
    from.value = '';
    to.value = '';
}

let searchTimer: number | undefined;
watch(filters, (next, prev) => {
    page.value = 1;
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(run, next.search !== prev.search ? 300 : 0);
});
watch(page, run);
onBeforeUnmount(() => window.clearTimeout(searchTimer));
onMounted(refresh);
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader
            title="Transactions"
            title-id="page-title"
            description="Everything you’ve recorded. Mistakes are voided or reversed, never deleted."
        >
            <template #actions>
                <Button variant="outline" icon-start="download" :loading="exporting" @click="exportCsv">Export CSV</Button>
                <Button icon-start="add" :disabled="!lists.accounts.value.length" @click="record(null)"
                    >New transaction</Button
                >
            </template>
        </PageHeader>

        <section aria-label="Filters" class="flex flex-wrap items-center gap-2 border-b border-border pb-6">
            <SearchField
                id="transaction-search"
                v-model="search"
                label="Search transactions"
                placeholder="Search transactions"
                :maxlength="LIMITS.search"
                class="w-full sm:w-96"
            />
            <FacetFilter v-model="type" label="Type" :options="transactionTypeOptions" />
            <FacetFilter v-model="status" label="Status" :options="statusOptions" />
            <FacetFilter v-model="accountId" label="Account" :options="accountOptions" />
            <DateRangeFilter v-model:start="from" v-model:end="to" label="Dates" />
            <Button v-if="filtered" variant="ghost" size="sm" icon-start="close" @click="clear">Clear</Button>
            <Text v-if="data" role="label" tone="muted" tabular aria-live="polite" class="ml-auto">
                {{ data.meta.total }} {{ data.meta.total === 1 ? 'transaction' : 'transactions' }}
            </Text>
        </section>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <Card v-else-if="loading && !data" class="flex flex-col gap-3" aria-hidden="true">
            <Skeleton v-for="n in 8" :key="n" class="h-10" />
        </Card>
        <template v-else-if="data?.data.length">
            <TransactionTable
                :transactions="data.data"
                :account-id="accountId || null"
                @edit="record"
                @void="actions.voiding.value = $event"
                @reverse="actions.reversing.value = $event"
            />
            <Pagination v-model:page="page" :last-page="data.meta.last_page" />
        </template>
        <EmptyState
            v-else
            icon="transfer"
            :title="filtered ? 'No transactions match' : 'No transactions yet'"
            :message="filtered ? undefined : 'Record income, spending or a transfer between your accounts.'"
        >
            <Button v-if="filtered" variant="outline" @click="clear">Clear filters</Button>
            <Button v-else-if="lists.accounts.value.length" icon-start="add" @click="record(null)">Record one</Button>
            <Button v-else icon-start="add" :to="{ name: 'financial-accounts' }">Add an account first</Button>
        </EmptyState>

        <TransactionFormDialog
            v-model:open="formOpen"
            :transaction="editing"
            :accounts="lists.accounts.value"
            :categories="lists.categories.value"
            :default-account-id="accountId || null"
            @saved="refresh"
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
