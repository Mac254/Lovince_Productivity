<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { Budget, Currency } from '@focus/types';
import type { MenuItem } from '@focus/ui';
import { todayIso } from '@/lib/dates';
import { useAccountingLists } from '@/features/accounting/useAccounting';
import {
    budgetState,
    currencyOptions,
    elapsedShare,
    formatMoney,
    monthOf,
    type Period,
} from '@/features/accounting/money';

const toast = useToast();
const period = ref<Period>(monthOf(todayIso()));
const currency = ref<Currency | ''>('');
const lists = useAccountingLists();
const { data, loading, error, run } = useAsync((signal) =>
    api.accounting.budgets.list(period.value.from.slice(0, 7), { signal }),
);

const currencies = computed(() => [...new Set(lists.accounts.value.map((a) => a.currency))].sort() as Currency[]);
const shown = computed<Currency>(
    () => currency.value || (data.value?.data[0]?.currency ?? currencies.value[0] ?? 'KES'),
);
const budgets = computed(() => (data.value?.data ?? []).filter((b) => b.currency === shown.value));
const options = computed(() => currencyOptions([...new Set((data.value?.data ?? []).map((b) => b.currency))]));

const today = todayIso();
const elapsed = computed(() => elapsedShare(period.value, today));
const isCurrent = computed(() => elapsed.value > 0 && elapsed.value < 1);
const daysLeft = computed(() => Number(period.value.to.slice(8, 10)) - Number(today.slice(8, 10)) + 1);

const totals = computed(() => {
    const amount = budgets.value.reduce((sum, b) => sum + b.amount, 0);
    const spent = budgets.value.reduce((sum, b) => sum + b.spent, 0);
    return { amount, spent, left: amount - spent };
});
const money = (minor: number) => formatMoney(minor, shown.value);
const percent = (b: { spent: number; amount: number }) => (b.amount ? Math.round((b.spent / b.amount) * 100) : 0);

const states = {
    over: { tone: 'danger', bar: 'danger', label: 'Over budget' },
    ahead: { tone: 'warning', bar: 'warning', label: 'Spending fast' },
    'on-track': { tone: 'neutral', bar: 'accent', label: 'On track' },
} as const;

const formOpen = ref(false);
const editing = ref<Budget | null>(null);
const removing = ref<Budget | null>(null);
const removingBusy = ref(false);

function open(budget: Budget | null): void {
    editing.value = budget;
    formOpen.value = true;
}

function menu(budget: Budget): MenuItem[] {
    return [
        { label: 'Change limit', icon: 'edit', onSelect: () => open(budget) },
        { label: 'Remove', icon: 'trash', destructive: true, onSelect: () => (removing.value = budget) },
    ];
}

async function confirmRemove(): Promise<void> {
    if (!removing.value) return;
    removingBusy.value = true;
    try {
        await api.accounting.budgets.remove(removing.value.id);
        toast.success('Budget removed');
        removing.value = null;
        await run();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not remove the budget.'));
    } finally {
        removingBusy.value = false;
    }
}

watch(period, run);
onMounted(() => Promise.all([run(), lists.load()]));
</script>

<template>
    <div class="flex flex-col gap-8">
        <PageHeader
            title="Budgets"
            title-id="page-title"
            description="A monthly limit for each spending category, checked against what you have posted."
        >
            <template #actions>
                <Button icon-start="add" :disabled="!lists.accounts.value.length" @click="open(null)"
                    >New budget</Button
                >
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <PeriodPicker v-model="period" />
            <Select
                v-if="options.length > 1"
                id="budget-currency-filter"
                v-model="currency"
                label="Currency"
                :options="options"
                class="w-40"
            />
        </div>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="loading && !data" class="grid gap-4 sm:grid-cols-3" aria-hidden="true">
            <Skeleton v-for="n in 3" :key="n" class="h-36" />
        </div>
        <EmptyState
            v-else-if="data && !budgets.length"
            icon="target"
            title="No budgets yet"
            message="Pick the categories you want to keep an eye on, such as food or transport, and give each a monthly limit."
        >
            <Button icon-start="add" :disabled="!lists.accounts.value.length" @click="open(null)">New budget</Button>
        </EmptyState>
        <template v-else-if="data">
            <div class="grid gap-4 sm:grid-cols-3">
                <MoneyTile
                    label="Budgeted"
                    :value="money(totals.amount)"
                    icon="target"
                    :hint="`${budgets.length} ${budgets.length === 1 ? 'category' : 'categories'}`"
                />
                <MoneyTile
                    label="Spent"
                    :value="money(totals.spent)"
                    icon="tag"
                    motion="wiggle"
                    :hint="`${percent(totals)}% of the total`"
                />
                <MoneyTile
                    :label="totals.left < 0 ? 'Over' : 'Left'"
                    :value="money(Math.abs(totals.left))"
                    icon="wallet"
                    emphasis
                    :hint="
                        isCurrent && totals.left > 0
                            ? `About ${money(Math.floor(totals.left / daysLeft))} a day for ${daysLeft} ${daysLeft === 1 ? 'day' : 'days'}`
                            : undefined
                    "
                />
            </div>

            <Card as="section" aria-labelledby="budgets-heading" class="flex flex-col gap-2">
                <CardHeader title="Categories" title-id="budgets-heading" icon="tag" class="mb-0" />
                <ul class="flex flex-col">
                    <li
                        v-for="budget in budgets"
                        :key="budget.id"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-border py-4 last:border-b-0"
                    >
                        <div class="min-w-0 flex-1 sm:w-48 sm:flex-none">
                            <p class="truncate font-semibold">{{ budget.category.name }}</p>
                            <Badge
                                v-if="budgetState(budget, elapsed) !== 'on-track'"
                                dot
                                :tone="states[budgetState(budget, elapsed)].tone"
                            >
                                {{ states[budgetState(budget, elapsed)].label }}
                            </Badge>
                        </div>
                        <div class="order-last flex w-full flex-col gap-1 sm:order-none sm:w-auto sm:flex-1">
                            <ProgressBar
                                :value="percent(budget)"
                                :tone="states[budgetState(budget, elapsed)].bar"
                                :label="`${budget.category.name}: ${percent(budget)}% of the budget spent`"
                            />
                            <p class="text-caption text-fg-muted tabular">
                                {{ money(budget.spent) }} of {{ money(budget.amount) }}
                            </p>
                        </div>
                        <p
                            class="text-end font-semibold whitespace-nowrap tabular"
                            :class="budget.remaining < 0 ? 'text-danger-fg' : 'text-fg'"
                        >
                            {{ money(Math.abs(budget.remaining)) }}
                            <span class="block text-caption font-normal text-fg-muted">{{
                                budget.remaining < 0 ? 'over' : 'left'
                            }}</span>
                        </p>
                        <DropdownMenu
                            :items="menu(budget)"
                            :label="`Actions for the ${budget.category.name} budget`"
                            size="sm"
                        />
                    </li>
                </ul>
            </Card>
        </template>

        <BudgetFormDialog
            v-model:open="formOpen"
            :budget="editing"
            :budgets="data?.data ?? []"
            :categories="lists.categories.value"
            :currencies="currencies"
            @saved="run"
        />
        <ConfirmDialog
            :open="!!removing"
            title="Remove budget?"
            :message="`The ${removing?.category.name} limit goes. Your transactions stay as they are.`"
            confirm-label="Remove"
            :loading="removingBusy"
            @update:open="!$event && (removing = null)"
            @confirm="confirmRemove"
        />
    </div>
</template>
