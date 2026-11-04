<script setup lang="ts">
import type { AccountingInsight, Currency } from '@focus/types';
import { formatMoney } from './money';

const props = defineProps<{ insights: AccountingInsight[]; currency: Currency; monthly: boolean }>();

const tones = {
    warning: 'warning',
    success: 'info',
    info: 'info',
} as const;

function message(insight: AccountingInsight): string {
    const money = (amount: number) => formatMoney(amount, props.currency);
    const previous = props.monthly ? 'last month' : 'the previous period';
    switch (insight.key) {
        case 'spending_change':
            return insight.percent > 0
                ? `Spending is up ${insight.percent}% on ${previous}, ${money(insight.amount)} more.`
                : `Spending is down ${-insight.percent}% on ${previous}, ${money(-insight.amount)} less.`;
        case 'top_category':
            return `${insight.category} is ${insight.percent}% of your spending this period (${money(insight.amount)}).`;
        case 'negative_cash_flow':
            return `${money(insight.amount)} more went out than came in this period.`;
        case 'pending':
            return `${insight.count} pending ${insight.count === 1 ? 'payment' : 'payments'} of ${money(insight.amount)} will lower your balance when they clear.`;
    }
}
</script>

<template>
    <ul class="flex flex-col gap-3">
        <li v-for="insight in insights" :key="insight.key">
            <Alert :tone="tones[insight.tone]" :icon="insight.tone === 'success' ? 'check-circle' : undefined">
                {{ message(insight) }}
            </Alert>
        </li>
    </ul>
</template>
