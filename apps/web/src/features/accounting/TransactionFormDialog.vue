<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import {
    LIMITS,
    type AccountingCategory,
    type FinancialAccount,
    type Frequency,
    type RecurringTransaction,
    type RecurringTransactionInput,
    type Transaction,
    type TransactionDirection,
    type TransactionInput,
    type TransactionType,
} from '@focus/types';
import { todayIso } from '@/lib/dates';
import { decimalsOf, frequencyLabels, isAmount, toDecimal, transactionTypeLabels } from './money';

type Kind = 'income' | 'expense' | 'transfer' | 'other';
type OtherType = Exclude<TransactionType, 'income' | 'expense' | 'transfer'>;

/** Records a transaction, or with "Repeats" set, a recurring payment; `rule` edits an existing one. */
const props = defineProps<{
    transaction?: Transaction | null;
    rule?: RecurringTransaction | null;
    /** Start a new record as a monthly recurring payment. */
    recurring?: boolean;
    accounts: FinancialAccount[];
    categories: AccountingCategory[];
    defaultAccountId?: number | null;
}>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [] }>();

const toast = useToast();
const kinds: { value: Kind; label: string }[] = [
    { value: 'expense', label: 'Expense' },
    { value: 'income', label: 'Income' },
    { value: 'transfer', label: 'Transfer' },
    { value: 'other', label: 'Other' },
];
const otherTypes: OtherType[] = ['refund', 'interest', 'fee', 'adjustment'];
const repeatOptions = [
    { value: '' as const, label: 'Doesn’t repeat' },
    ...(['weekly', 'monthly', 'yearly'] as const).map((value) => ({ value, label: frequencyLabels[value] })),
];
const directionOptions: { value: TransactionDirection; label: string }[] = [
    { value: 'in', label: 'Adds money' },
    { value: 'out', label: 'Removes money' },
];
const statusOptions = computed(() =>
    form.repeats
        ? [
              { value: 'posted' as const, label: 'Posted on the day' },
              { value: 'pending' as const, label: 'Pending until I confirm' },
          ]
        : [
              { value: 'posted' as const, label: 'Posted — it has happened' },
              { value: 'pending' as const, label: 'Pending — not cleared yet' },
          ],
);

const form = reactive({
    kind: 'expense' as Kind,
    other: 'refund' as OtherType,
    direction: 'in' as TransactionDirection,
    account_id: null as number | null,
    to_account_id: null as number | null,
    category_id: null as number | null,
    amount: '',
    date: todayIso(),
    repeats: '' as Frequency | '',
    end_date: '',
    status: 'posted' as 'posted' | 'pending',
    description: '',
    reference: '' as string | null,
    notes: '' as string | null,
});
const errors = ref<Record<string, string>>({});
const saving = ref(false);
/** One key per form opening, so a retried submission after a timeout never posts twice. */
let submissionKey = '';

const isEdit = computed(() => !!props.transaction || !!props.rule);
/** Refunds and adjustments are one-offs, so they can't repeat. */
const otherOptions = computed(() =>
    otherTypes
        .filter((value) => !form.repeats || value === 'interest' || value === 'fee')
        .map((value) => ({ value, label: transactionTypeLabels[value] })),
);
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
    const t = props.transaction ?? props.rule;
    submissionKey = crypto.randomUUID();
    const kind: Kind = !t ? 'expense' : ['income', 'expense', 'transfer'].includes(t.type) ? (t.type as Kind) : 'other';
    Object.assign(form, {
        kind,
        other: kind === 'other' ? (t!.type as OtherType) : 'refund',
        direction: props.transaction?.direction ?? 'in',
        account_id: t?.account.id ?? props.defaultAccountId ?? openAccounts.value[0]?.id ?? null,
        to_account_id: t?.to_account?.id ?? null,
        category_id: t?.category?.id ?? null,
        amount: t ? toDecimal(t.amount, t.currency) : '',
        date: props.rule?.start_date ?? props.transaction?.date ?? todayIso(),
        repeats: props.rule?.frequency ?? (props.recurring && !props.transaction ? 'monthly' : ''),
        end_date: props.rule?.end_date ?? '',
        status: t?.status === 'pending' ? 'pending' : 'posted',
        description: t?.description ?? '',
        reference: t?.reference ?? '',
        notes: t?.notes ?? '',
    });
    errors.value = {};
});

watch(
    () => form.repeats,
    (repeats) => {
        if (repeats && form.kind === 'other' && (form.other === 'refund' || form.other === 'adjustment'))
            form.other = 'fee';
    },
);

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
    if (form.repeats && form.end_date && form.end_date < form.date)
        errors.value.end_date = 'The last payment can’t come before the first.';
    return Object.keys(errors.value).length === 0;
}

async function saveRule(): Promise<void> {
    const payload: RecurringTransactionInput = {
        type: type.value as RecurringTransactionInput['type'],
        account_id: form.account_id!,
        to_account_id: type.value === 'transfer' ? form.to_account_id : null,
        category_id: categoryKind.value ? form.category_id || null : null,
        amount: form.amount.trim(),
        status: form.status,
        description: form.description.trim(),
        reference: form.reference?.trim() || null,
        notes: form.notes?.trim() || null,
        frequency: form.repeats || 'monthly',
        start_date: form.date || todayIso(),
        end_date: form.end_date || null,
    };
    if (
        props.rule &&
        payload.start_date === props.rule.start_date &&
        payload.frequency === props.rule.frequency &&
        payload.end_date === props.rule.end_date
    ) {
        delete payload.start_date;
        delete payload.frequency;
        delete payload.end_date;
    }
    const saved = props.rule
        ? await api.accounting.recurring.update(props.rule.id, payload)
        : await api.accounting.recurring.create(payload);
    toast.success(
        props.rule
            ? 'Recurring payment updated'
            : saved.occurrences
              ? `Repeats ${frequencyLabels[saved.frequency].toLowerCase()} · ${saved.occurrences} recorded so far`
              : `Repeats ${frequencyLabels[saved.frequency].toLowerCase()}`,
    );
}

async function submit(): Promise<void> {
    if (!validate()) return;
    saving.value = true;
    if (form.repeats || props.rule) {
        try {
            await saveRule();
            open.value = false;
            emit('saved');
        } catch (e) {
            if (isApiError(e) && e.isValidation) {
                const { start_date, ...rest } = e.fieldErrors();
                errors.value = { ...rest, ...(start_date ? { date: start_date } : {}) };
            } else toast.error(errorMessage(e, 'Could not save the recurring payment.'));
        } finally {
            saving.value = false;
        }
        return;
    }
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
        if (props.transaction) await api.accounting.transactions.update(props.transaction.id, payload);
        else await api.accounting.transactions.create(payload, submissionKey);
        toast.success(isEdit.value ? 'Transaction updated' : 'Transaction recorded');
        open.value = false;
        emit('saved');
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
        :title="rule ? 'Edit recurring payment' : isEdit ? 'Edit transaction' : 'New transaction'"
        :description="
            locked
                ? 'This transaction is settled, so only its description, reference and notes can change.'
                : form.repeats
                  ? 'Each payment is recorded on its day. Ones already past are recorded when you save.'
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
                <DatePicker
                    id="transaction-date"
                    v-model="form.date"
                    :label="form.repeats ? 'First payment' : 'Date'"
                    :error="errors.date"
                />
                <Select
                    v-if="!transaction"
                    id="transaction-repeats"
                    v-model="form.repeats"
                    label="Repeats"
                    :options="rule ? repeatOptions.slice(1) : repeatOptions"
                    :error="errors.frequency"
                />
                <DatePicker
                    v-if="form.repeats"
                    id="transaction-end-date"
                    v-model="form.end_date"
                    label="Last payment"
                    optional
                    :min="form.date"
                    :error="errors.end_date"
                />
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
                    :label="form.repeats ? 'Each payment' : 'Status'"
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
                isEdit ? 'Save changes' : form.repeats ? 'Save' : 'Record'
            }}</Button>
        </template>
    </Dialog>
</template>
