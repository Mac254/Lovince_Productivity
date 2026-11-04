<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { Currency, RecurringTransaction } from '@focus/types';
import type { MenuItem } from '@focus/ui';
import { daysUntil, formatDate, todayIso } from '@/lib/dates';
import { useAccountingLists } from '@/features/accounting/useAccounting';
import { formatMoney, frequencyLabels, monthlyAmount } from '@/features/accounting/money';

const toast = useToast();
const lists = useAccountingLists();
const { data, loading, error, run } = useAsync((signal) => api.accounting.recurring.list({ signal }));

const rules = computed(() => data.value ?? []);
const active = computed(() => rules.value.filter((r) => !r.paused && r.next_date));
/** Totals use the currency most rules are in; the table shows every rule in its own. */
const currency = computed<Currency>(() => {
    const counts = new Map<Currency, number>();
    for (const r of active.value) counts.set(r.currency, (counts.get(r.currency) ?? 0) + 1);
    return [...counts].sort((a, b) => b[1] - a[1])[0]?.[0] ?? 'KES';
});
const inCurrency = computed(() => active.value.filter((r) => r.currency === currency.value));
const sum = (list: RecurringTransaction[]) => list.reduce((total, r) => total + monthlyAmount(r), 0);
const totals = computed(() => {
    const out = inCurrency.value.filter((r) => r.direction === 'out' && r.type !== 'transfer');
    const income = inCurrency.value.filter((r) => r.direction === 'in');
    const saved = inCurrency.value.filter((r) => r.type === 'transfer');
    const soon = inCurrency.value.filter((r) => daysUntil(r.next_date!) <= 30);
    return { out: sum(out), income: sum(income), saved: sum(saved), soon };
});

const money = (minor: number, code: string = currency.value) => formatMoney(minor, code);
const signed = (r: RecurringTransaction) =>
    formatMoney(r.direction === 'out' && r.type !== 'transfer' ? -r.amount : r.amount, r.currency, {
        signed: r.type !== 'transfer',
    });
const route = (r: RecurringTransaction) =>
    r.type === 'transfer'
        ? `${r.account.name} → ${r.to_account?.name}`
        : [r.account.name, r.category?.name].filter(Boolean).join(' · ');
const when = (r: RecurringTransaction) => {
    if (!r.next_date) return 'Ended';
    if (r.paused) return 'Paused';
    const days = daysUntil(r.next_date);
    return days === 0 ? 'Today' : days === 1 ? 'Tomorrow' : formatDate(r.next_date);
};

const formOpen = ref(false);
const editing = ref<RecurringTransaction | null>(null);
const removing = ref<RecurringTransaction | null>(null);
const removingBusy = ref(false);

function open(rule: RecurringTransaction | null): void {
    editing.value = rule;
    formOpen.value = true;
}

async function setPaused(rule: RecurringTransaction, paused: boolean): Promise<void> {
    try {
        await api.accounting.recurring.update(rule.id, { paused });
        toast.success(paused ? 'Paused. Nothing is recorded until you resume.' : 'Resumed from today');
        await run();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not update it.'));
    }
}

function menu(rule: RecurringTransaction): MenuItem[] {
    const items: MenuItem[] = [{ label: 'Edit', icon: 'edit', onSelect: () => open(rule) }];
    if (rule.next_date) {
        items.push(
            rule.paused
                ? { label: 'Resume', icon: 'retry', onSelect: () => setPaused(rule, false) }
                : { label: 'Pause', icon: 'hourglass', onSelect: () => setPaused(rule, true) },
        );
    }
    items.push({ label: 'Delete', icon: 'trash', destructive: true, onSelect: () => (removing.value = rule) });
    return items;
}

async function confirmRemove(): Promise<void> {
    if (!removing.value) return;
    removingBusy.value = true;
    try {
        await api.accounting.recurring.remove(removing.value.id);
        toast.success('Recurring payment deleted');
        removing.value = null;
        await run();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete it.'));
    } finally {
        removingBusy.value = false;
    }
}

const today = todayIso();
onMounted(() => Promise.all([run(), lists.load()]));
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader
            title="Recurring"
            title-id="page-title"
            description="Rent, salary, subscriptions and savings that repeat. Each is recorded on its day, posted or left pending for you to confirm."
        >
            <template #actions>
                <Button icon-start="add" :disabled="!lists.accounts.value.length" @click="open(null)"
                    >New recurring payment</Button
                >
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="loading && !data" class="grid gap-4 sm:grid-cols-3" aria-hidden="true">
            <Skeleton v-for="n in 3" :key="n" class="h-36" />
        </div>
        <EmptyState
            v-else-if="data && !rules.length"
            icon="calendar-clock"
            title="Nothing repeats yet"
            message="Add the payments that come round every week, month or year, and they record themselves."
        >
            <Button icon-start="add" :disabled="!lists.accounts.value.length" @click="open(null)"
                >New recurring payment</Button
            >
        </EmptyState>
        <template v-else-if="data">
            <div class="grid gap-4 sm:grid-cols-3">
                <MoneyTile
                    label="Going out a month"
                    :value="money(totals.out)"
                    icon="tag"
                    motion="wiggle"
                    :hint="
                        totals.saved
                            ? `Plus ${money(totals.saved)} moved to savings`
                            : 'Bills and subscriptions, on average'
                    "
                />
                <MoneyTile
                    label="Coming in a month"
                    :value="money(totals.income)"
                    icon="arrow-up-right"
                    motion="lift"
                    hint="Salary, interest and other income"
                />
                <MoneyTile
                    label="Due in 30 days"
                    :value="money(totals.soon.reduce((s, r) => s + (r.direction === 'out' ? r.amount : 0), 0))"
                    icon="calendar-clock"
                    emphasis
                    :hint="`${totals.soon.length} ${totals.soon.length === 1 ? 'payment' : 'payments'} coming up`"
                />
            </div>

            <Card as="section" aria-labelledby="recurring-heading" class="flex flex-col gap-2">
                <CardHeader title="Schedule" title-id="recurring-heading" icon="calendar-clock" class="mb-0" />
                <Table caption="Recurring payments" label="Recurring payments">
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-full">Payment</TableHead>
                            <TableHead class="hidden sm:table-cell">Repeats</TableHead>
                            <TableHead>Next</TableHead>
                            <TableHead align="end">Amount</TableHead>
                            <TableHead><VisuallyHidden>Actions</VisuallyHidden></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="rule in rules"
                            :key="rule.id"
                            :class="(rule.paused || !rule.next_date) && 'text-fg-muted'"
                        >
                            <TableCell>
                                <p class="font-medium" :class="!rule.paused && rule.next_date && 'text-fg'">
                                    {{ rule.description }}
                                </p>
                                <p class="text-caption text-fg-muted">
                                    {{ route(rule)
                                    }}<span class="sm:hidden"> · {{ frequencyLabels[rule.frequency] }}</span>
                                </p>
                            </TableCell>
                            <TableCell class="hidden whitespace-nowrap sm:table-cell">
                                {{ frequencyLabels[rule.frequency] }}
                                <Badge v-if="rule.status === 'pending'" dot tone="warning" class="ms-2"
                                    >Confirm each</Badge
                                >
                            </TableCell>
                            <TableCell class="whitespace-nowrap">
                                <Badge
                                    v-if="rule.paused || !rule.next_date"
                                    :tone="rule.paused ? 'warning' : 'neutral'"
                                    >{{ when(rule) }}</Badge
                                >
                                <span
                                    v-else
                                    :class="rule.next_date <= today ? 'font-semibold text-highlight-fg' : ''"
                                    >{{ when(rule) }}</span
                                >
                            </TableCell>
                            <TableCell
                                numeric
                                align="end"
                                class="font-semibold whitespace-nowrap"
                                :class="rule.direction === 'in' && rule.type !== 'transfer' ? 'text-success-fg' : ''"
                            >
                                {{ signed(rule) }}
                            </TableCell>
                            <TableCell>
                                <DropdownMenu
                                    :items="menu(rule)"
                                    :label="`Actions for “${rule.description}”`"
                                    size="sm"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </Card>
        </template>

        <TransactionFormDialog
            v-model:open="formOpen"
            :rule="editing"
            recurring
            :accounts="lists.accounts.value"
            :categories="lists.categories.value"
            @saved="run"
        />
        <ConfirmDialog
            :open="!!removing"
            title="Delete recurring payment?"
            :message="`“${removing?.description}” stops repeating. Payments already recorded stay in your books.`"
            confirm-label="Delete"
            :loading="removingBusy"
            @update:open="!$event && (removing = null)"
            @confirm="confirmRemove"
        />
    </div>
</template>
