<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import {
    LIMITS,
    type AccountingCategory,
    type FinancialAccount,
    type Transaction,
    type TransactionDirection,
    type TransactionInput,
    type TransactionType,
} from '@focus/types';
import { todayIso } from '@/lib/dates';
import { decimalsOf, isAmount, toDecimal, transactionTypeLabels } from './money';

type Kind = 'income' | 'expense' | 'transfer' | 'other';
type OtherType = Exclude<TransactionType, 'income' | 'expense' | 'transfer'>;

const props = defineProps<{
    transaction?: Transaction | null;
    accounts: FinancialAccount[];
    categories: AccountingCategory[];
    defaultAccountId?: number | null;
}>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [transaction: Transaction] }>();

const toast = useToast();
const kinds: { value: Kind; label: string }[] = [
    { value: 'expense', label: 'Expense' },
    { value: 'income', label: 'Income' },
    { value: 'transfer', label: 'Transfer' },
    { value: 'other', label: 'Other' },
];
const otherTypes: OtherType[] = ['refund', 'interest', 'fee', 'adjustment'];
const otherOptions = otherTypes.map((value) => ({ value, label: transactionTypeLabels[value] }));
const directionOptions: { value: TransactionDirection; label: string }[] = [
    { value: 'in', label: 'Adds money' },
    { value: 'out', label: 'Removes money' },
];
const statusOptions = [
    { value: 'posted' as const, label: 'Posted — it has happened' },
    { value: 'pending' as const, label: 'Pending — not cleared yet' },
];

const form = reactive({
    kind: 'expense' as Kind,
    other: 'refund' as OtherType,
    direction: 'in' as TransactionDirection,
    account_id: null as number | null,
    to_account_id: null as number | null,
    category_id: null as number | null,
    amount: '',
    date: todayIso(),
    status: 'posted' as 'posted' | 'pending',
    description: '',
    reference: '' as string | null,
    notes: '' as string | null,
});
const errors = ref<Record<string, string>>({});
const saving = ref(false);
/** One key per form opening, so a retried submission after a timeout never posts twice. */
let submissionKey = '';

const isEdit = computed(() => !!props.transaction);
const locked = computed(
    () =>
        !!props.transaction &&
        (props.transaction.status === 'reconciled' ||
            props.transaction.reversal_of_id !== null ||
            props.transaction.reversed_by_id !== null),
);
const type = computed<TransactionType>(() => (form.kind === 'other' ? form.other : form.kind));
const categoryKind = computed(() => {
    if (type.value === 'income' || type.value === 'interest') return 'income';
    if (type.value === 'transfer' || type.value === 'adjustment') return null;
    return 'expense';
});

const openAccounts = computed(() =>
    props.accounts.filter(
        (a) => !a.archived || a.id === props.transaction?.account.id || a.id === props.transaction?.to_account?.id,
    ),
);
const account = computed(() => props.accounts.find((a) => a.id === form.account_id) ?? null);
const currency = computed(() => account.value?.currency ?? 'KES');
const accountOptions = computed(() =>
    openAccounts.value.map((a) => ({ value: a.id, label: `${a.name} · ${a.currency}` })),
);
const toAccountOptions = computed(() =>
    openAccounts.value
        .filter((a) => a.id !== form.account_id && a.currency === currency.value)
        .map((a) => ({ value: a.id, label: a.name })),
);
const categoryOptions = computed(() => [
    { value: '' as const, label: 'Uncategorised' },
    ...props.categories
        .filter((c) => c.kind === categoryKind.value && (!c.archived || c.id === props.transaction?.category?.id))
        .map((c) => ({ value: c.id, label: c.name })),
]);

watch(open, (value) => {
    if (!value) return;
    const t = props.transaction;
    submissionKey = crypto.randomUUID();
    const kind: Kind = !t ? 'expense' : ['income', 'expense', 'transfer'].includes(t.type) ? (t.type as Kind) : 'other';
    Object.assign(form, {
        kind,
        other: kind === 'other' ? (t!.type as OtherType) : 'refund',
        direction: t?.direction ?? 'in',
        account_id: t?.account.id ?? props.defaultAccountId ?? openAccounts.value[0]?.id ?? null,
        to_account_id: t?.to_account?.id ?? null,
        category_id: t?.category?.id ?? null,
        amount: t ? toDecimal(t.amount, t.currency) : '',
        date: t?.date ?? todayIso(),
        status: t?.status === 'pending' ? 'pending' : 'posted',
        description: t?.description ?? '',
        reference: t?.reference ?? '',
        notes: t?.notes ?? '',
    });
    errors.value = {};
});

watch(categoryKind, () => {
    const current = props.categories.find((c) => c.id === form.category_id);
    if (current && current.kind !== categoryKind.value) form.category_id = null;
});

function validate(): boolean {
    errors.value = {};
    if (!form.account_id) errors.value.account_id = 'Choose an account.';
    if (type.value === 'transfer' && !form.to_account_id) errors.value.to_account_id = 'Choose where the money goes.';
    if (!isAmount(form.amount, currency.value)) {
        const decimals = decimalsOf(currency.value);
        errors.value.amount = decimals
            ? `Enter an amount above zero with up to ${decimals} decimal places.`
            : `Enter a whole amount above zero.`;
    }
    if (!form.description.trim()) errors.value.description = 'Say what it was for.';
    return Object.keys(errors.value).length === 0;
}

async function submit(): Promise<void> {
    if (!validate()) return;
    saving.value = true;
    const payload: TransactionInput = {
        type: type.value,
        account_id: form.account_id!,
        to_account_id: type.value === 'transfer' ? form.to_account_id : null,
        category_id: categoryKind.value ? form.category_id || null : null,
        direction: type.value === 'adjustment' ? form.direction : null,
        amount: form.amount.trim(),
        date: form.date || todayIso(),
        description: form.description.trim(),
        reference: form.reference?.trim() || null,
        notes: form.notes?.trim() || null,
    };
    if (!props.transaction || props.transaction.status === 'pending' || props.transaction.status === 'posted') {
        payload.status = form.status;
    }
    if (locked.value) {
        payload.type = undefined;
        payload.account_id = undefined;
        payload.to_account_id = undefined;
        payload.category_id = undefined;
        payload.direction = undefined;
        payload.amount = undefined;
        payload.date = undefined;
        payload.status = undefined;
    }
    try {
        const saved = props.transaction
            ? await api.accounting.transactions.update(props.transaction.id, payload)
            : await api.accounting.transactions.create(payload, submissionKey);
        toast.success(isEdit.value ? 'Transaction updated' : 'Transaction recorded');
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the transaction. Trying again is safe.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="isEdit ? 'Edit transaction' : 'New transaction'"
        :description="
            locked
                ? 'This transaction is settled, so only its description, reference and notes can change.'
                : 'Balances are worked out from what you record here.'
        "
        :busy="saving"
    >
        <form id="transaction-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <SegmentedControl v-if="!locked" v-model="form.kind" label="Kind of transaction" :options="kinds" />
            <div v-if="!locked && form.kind === 'other'" class="grid gap-6 sm:grid-cols-2">
                <Select id="transaction-other" v-model="form.other" label="Type" :options="otherOptions" />
                <Select
                    v-if="form.other === 'adjustment'"
                    id="transaction-direction"
                    v-model="form.direction"
                    label="Effect"
                    :options="directionOptions"
                    :error="errors.direction"
                />
            </div>
            <div v-if="!locked" class="grid gap-6 sm:grid-cols-2">
                <Select
                    id="transaction-account"
                    v-model="form.account_id"
                    :label="type === 'transfer' ? 'From account' : 'Account'"
                    :options="accountOptions"
                    :error="errors.account_id"
                />
                <Select
                    v-if="type === 'transfer'"
                    id="transaction-to-account"
                    v-model="form.to_account_id"
                    label="To account"
                    :options="toAccountOptions"
                    :error="errors.to_account_id"
                    :hint="toAccountOptions.length ? undefined : `Add another ${currency} account to transfer to.`"
                />
                <Select
                    v-else-if="categoryKind"
                    id="transaction-category"
                    v-model="form.category_id"
                    label="Category"
                    optional
                    :options="categoryOptions"
                    :error="errors.category_id"
                />
                <Input
                    id="transaction-amount"
                    v-model="form.amount"
                    :label="`Amount (${currency})`"
                    inputmode="decimal"
                    autocomplete="off"
                    placeholder="0.00"
                    required
                    :error="errors.amount"
                    autofocus
                />
                <DatePicker id="transaction-date" v-model="form.date" label="Date" :error="errors.date" />
            </div>
            <Input
                id="transaction-description"
                v-model="form.description"
                label="Description"
                :maxlength="LIMITS.accounting.description"
                required
                autocomplete="off"
                :error="errors.description"
            />
            <div class="grid gap-6 sm:grid-cols-2">
                <Input
                    id="transaction-reference"
                    v-model="form.reference"
                    label="Reference"
                    optional
                    hint="A receipt or M-Pesa code"
                    :maxlength="LIMITS.accounting.reference"
                    autocomplete="off"
                    :error="errors.reference"
                />
                <Select
                    v-if="!locked && (!transaction || transaction.status !== 'reconciled')"
                    id="transaction-status"
                    v-model="form.status"
                    label="Status"
                    :options="statusOptions"
                    :error="errors.status"
                />
            </div>
            <Textarea
                id="transaction-notes"
                v-model="form.notes"
                label="Notes"
                optional
                :maxlength="LIMITS.accounting.notes"
                :error="errors.notes"
            />
        </form>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Cancel</Button>
            <Button type="submit" form="transaction-form" :loading="saving">{{
                isEdit ? 'Save changes' : 'Record'
            }}</Button>
        </template>
    </Dialog>
</template>
