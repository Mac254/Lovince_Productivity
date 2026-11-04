<script setup lang="ts">
import { api } from '@focus/api-client';
import { todayIso } from '@/lib/dates';
import { formatMoney, monthOf } from '@/features/accounting/money';

/** This month's money at a glance, from the Money overview. */
const { data, error, run } = useAsync((signal) => api.accounting.dashboard(monthOf(todayIso()), { signal }));
const money = (minor: number) => (data.value ? formatMoney(minor, data.value.currency) : '—');
const budgetShare = computed(() => {
    const budgets = data.value?.budgets;
    return budgets && budgets.amount > 0 ? Math.round((budgets.spent / budgets.amount) * 100) : null;
});

onMounted(run);
</script>

<template>
    <Card as="section" data-stagger aria-labelledby="money-heading" class="group gap-4">
        <CardHeader title="Money" title-id="money-heading" icon="wallet" subtitle="This month" class="mb-0">
            <Button variant="ghost" size="sm" :to="{ name: 'accounting' }" icon-end="arrow-right">Open</Button>
        </CardHeader>
        <Text v-if="error" role="label" tone="muted">Money couldn’t load right now.</Text>
        <Skeleton v-else-if="!data" class="h-28" />
        <template v-else-if="data.accounts_count">
            <div>
                <p class="font-display text-title font-semibold tabular">{{ money(data.available_balance) }}</p>
                <Text role="caption" tone="muted"
                    >Available across {{ data.accounts_count }}
                    {{ data.accounts_count === 1 ? 'account' : 'accounts' }}</Text
                >
            </div>
            <dl class="grid grid-cols-2 gap-3 text-label">
                <div>
                    <dt class="text-fg-muted">In</dt>
                    <dd class="font-semibold text-success-fg tabular">{{ money(data.income) }}</dd>
                </div>
                <div>
                    <dt class="text-fg-muted">Out</dt>
                    <dd class="font-semibold tabular">{{ money(data.expenses) }}</dd>
                </div>
            </dl>
            <div v-if="budgetShare !== null" class="mt-auto flex flex-col gap-1">
                <Text role="caption" tone="muted" tabular>
                    {{ money(Math.abs(data.budgets.amount - data.budgets.spent)) }}
                    {{ data.budgets.spent > data.budgets.amount ? 'over budget' : 'left in budgets' }}
                </Text>
                <ProgressBar
                    :value="budgetShare"
                    :tone="budgetShare > 100 ? 'danger' : 'accent'"
                    :label="`${budgetShare}% of this month’s budgets spent`"
                />
            </div>
        </template>
        <Text v-else role="label" tone="muted">Add an account to see your balance and spending here.</Text>
    </Card>
</template>
