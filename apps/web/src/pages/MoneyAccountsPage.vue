<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { FinancialAccount } from '@focus/types';
import type { MenuItem } from '@focus/ui';
import { useAccountingLists } from '@/features/accounting/useAccounting';
import { accountTypeIcons, accountTypeLabels, formatMoney } from '@/features/accounting/money';

const toast = useToast();
const lists = useAccountingLists();
const { loading, error, run } = useAsync(() => lists.load());
const showArchived = ref(false);

const visible = computed(() => lists.accounts.value.filter((a) => showArchived.value || !a.archived));
const archivedCount = computed(() => lists.accounts.value.filter((a) => a.archived).length);

const formOpen = ref(false);
const editing = ref<FinancialAccount | null>(null);
const deleting = ref<FinancialAccount | null>(null);
const deletingBusy = ref(false);

function open(account: FinancialAccount | null): void {
    editing.value = account;
    formOpen.value = true;
}

async function setArchived(account: FinancialAccount, archived: boolean): Promise<void> {
    try {
        await api.accounting.accounts.update(account.id, { archived });
        toast.success(archived ? 'Account archived' : 'Account restored');
        await run();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not update the account.'));
    }
}

async function confirmDelete(): Promise<void> {
    if (!deleting.value) return;
    deletingBusy.value = true;
    try {
        await api.accounting.accounts.remove(deleting.value.id);
        toast.success('Account deleted');
        deleting.value = null;
        await run();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the account.'));
    } finally {
        deletingBusy.value = false;
    }
}

function menu(account: FinancialAccount): MenuItem[] {
    const empty = account.total_inflows === 0 && account.total_outflows === 0;
    return [
        { label: 'Edit', icon: 'edit', onSelect: () => open(account) },
        account.archived
            ? { label: 'Restore', icon: 'undo', onSelect: () => setArchived(account, false) }
            : { label: 'Archive', icon: 'lock', onSelect: () => setArchived(account, true) },
        ...(empty ? [{ label: 'Delete', icon: 'trash' as const, destructive: true, onSelect: () => (deleting.value = account) }] : []),
    ];
}

onMounted(run);
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader
            title="Accounts"
            title-id="page-title"
            description="Balances come from your opening balance and the transactions you record."
        >
            <template #actions>
                <Button icon-start="add" @click="open(null)">New account</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="loading && !lists.accounts.value.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-hidden="true">
            <Skeleton v-for="n in 3" :key="n" class="h-44" />
        </div>
        <template v-else-if="lists.accounts.value.length">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Card v-for="account in visible" :key="account.id" class="group flex flex-col gap-4" :class="account.archived && 'opacity-70'">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="inline-flex size-(--control-md) shrink-0 items-center justify-center rounded-md bg-surface-raised text-highlight-fg">
                                <Icon :name="accountTypeIcons[account.type]" size="md" motion="pop" />
                            </span>
                            <div class="min-w-0">
                                <RouterLink
                                    :to="{ name: 'transactions', query: { account: account.id } }"
                                    class="block truncate font-semibold underline-offset-4 hover:underline"
                                    >{{ account.name }}</RouterLink
                                >
                                <Text as="p" role="caption" tone="muted">
                                    {{ accountTypeLabels[account.type] }} · {{ account.currency }}
                                    <template v-if="account.archived"> · archived</template>
                                </Text>
                            </div>
                        </div>
                        <DropdownMenu :items="menu(account)" :label="`Actions for ${account.name}`" size="sm" />
                    </div>
                    <div>
                        <p class="font-display text-title font-semibold tracking-tight tabular">
                            {{ formatMoney(account.balance, account.currency) }}
                        </p>
                        <Text v-if="account.available_balance !== account.balance" as="p" role="caption" tone="muted">
                            {{ formatMoney(account.available_balance, account.currency) }} available after pending payments
                        </Text>
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-label">
                        <div>
                            <dt class="text-fg-muted">Money in</dt>
                            <dd class="font-semibold tabular">{{ formatMoney(account.total_inflows, account.currency) }}</dd>
                        </div>
                        <div>
                            <dt class="text-fg-muted">Money out</dt>
                            <dd class="font-semibold tabular">{{ formatMoney(account.total_outflows, account.currency) }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>
            <div v-if="archivedCount" class="flex items-center gap-3">
                <Switch v-model="showArchived" label="Show archived accounts" />
                <Text as="span" tone="muted">Show {{ archivedCount }} archived</Text>
            </div>
        </template>
        <EmptyState
            v-else
            icon="wallet"
            title="No accounts yet"
            message="Add cash, a bank account, M-Pesa, a SACCO or a card. Transfers between them are never counted as spending."
        >
            <Button icon-start="add" @click="open(null)">Add an account</Button>
        </EmptyState>

        <CategoryManager :categories="lists.categories.value" @changed="run" />

        <AccountFormDialog
            v-model:open="formOpen"
            :account="editing"
            :default-currency="lists.accounts.value[0]?.currency"
            @saved="run"
        />
        <ConfirmDialog
            :open="!!deleting"
            title="Delete account?"
            :message="`“${deleting?.name}” has no transactions, so it will be removed completely.`"
            :loading="deletingBusy"
            @update:open="!$event && (deleting = null)"
            @confirm="confirmDelete"
        />
    </div>
</template>
