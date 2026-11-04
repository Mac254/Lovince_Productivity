<script setup lang="ts">
import type { Transaction } from '@focus/types';
import type { MenuItem } from '@focus/ui';
import { formatDate } from '@/lib/dates';
import { flowOf, signedAmount, transactionStatusLabels, transactionStatusTones, transactionTypeLabels } from './money';

const props = defineProps<{ transactions: Transaction[]; accountId?: number | null; compact?: boolean }>();
const emit = defineEmits<{ edit: [transaction: Transaction]; void: [transaction: Transaction]; reverse: [transaction: Transaction] }>();

const amountClass = (t: Transaction) =>
    ({ in: 'text-success-fg', out: 'text-fg', transfer: 'text-fg-muted' })[flowOf(t, props.accountId)];

function menu(t: Transaction): MenuItem[] {
    if (t.status === 'voided') return [];
    const settled = t.reversal_of_id !== null || t.reversed_by_id !== null;
    const items: MenuItem[] = [{ label: 'Edit', icon: 'edit', onSelect: () => emit('edit', t) }];
    if (!settled && (t.status === 'posted' || t.status === 'reconciled')) {
        items.push({ label: 'Reverse', icon: 'undo', onSelect: () => emit('reverse', t) });
    }
    if (!settled && t.status !== 'reconciled') {
        items.push({ label: 'Void', icon: 'forbidden', destructive: true, onSelect: () => emit('void', t) });
    }
    return items;
}

function where(t: Transaction): string {
    if (t.type === 'transfer') return `${t.account.name} → ${t.to_account?.name ?? ''}`;
    return t.account.name;
}
</script>

<template>
    <Card>
        <Table caption="Transactions" label="Transactions">
            <TableHeader>
                <TableRow>
                    <TableHead class="hidden sm:table-cell">Date</TableHead>
                    <TableHead class="w-full">Description</TableHead>
                    <TableHead v-if="!compact" class="hidden xl:table-cell">Account</TableHead>
                    <TableHead v-if="!compact" class="hidden md:table-cell">Status</TableHead>
                    <TableHead align="end">Amount</TableHead>
                    <TableHead v-if="!compact" align="end"><VisuallyHidden>Actions</VisuallyHidden></TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="t in transactions" :key="t.id" :class="t.status === 'voided' && 'opacity-60'">
                    <TableCell numeric class="hidden whitespace-nowrap text-fg-muted sm:table-cell">{{ formatDate(t.date) }}</TableCell>
                    <TableCell>
                        <div class="flex flex-col sm:min-w-48">
                            <span class="font-medium" :class="t.status === 'voided' && 'line-through'">{{
                                t.description
                            }}</span>
                            <Text as="span" role="caption" tone="muted">
                                <span class="sm:hidden">{{ formatDate(t.date) }} · </span>
                                {{ t.category?.name ?? transactionTypeLabels[t.type] }}
                                <span v-if="!compact" class="xl:hidden"> · {{ where(t) }}</span>
                                <span v-if="!compact && t.status !== 'posted'" class="md:hidden">
                                    · {{ transactionStatusLabels[t.status] }}</span
                                >
                                <template v-if="t.reference"> · {{ t.reference }}</template>
                                <template v-if="t.reversal_of_id"> · reversal</template>
                                <template v-else-if="t.reversed_by_id"> · reversed</template>
                            </Text>
                        </div>
                    </TableCell>
                    <TableCell v-if="!compact" class="hidden whitespace-nowrap text-fg-muted xl:table-cell">{{ where(t) }}</TableCell>
                    <TableCell v-if="!compact" class="hidden md:table-cell">
                        <Badge dot :tone="transactionStatusTones[t.status]">{{ transactionStatusLabels[t.status] }}</Badge>
                    </TableCell>
                    <TableCell numeric align="end" class="font-semibold whitespace-nowrap" :class="amountClass(t)">
                        {{ signedAmount(t, accountId) }}
                    </TableCell>
                    <TableCell v-if="!compact" align="end">
                        <DropdownMenu
                            v-if="menu(t).length"
                            :items="menu(t)"
                            :label="`Actions for “${t.description}”`"
                            size="sm"
                        />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </Card>
</template>
