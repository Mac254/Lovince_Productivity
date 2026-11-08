<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { LIMITS, type Currency, type FinancialAccount, type FinancialGoal, type FinancialGoalKind } from '@focus/types';
import { todayIso } from '@/lib/dates';
import { currencyOptions, decimalsOf, goalKindOptions, isAmount, toDecimal } from './money';

/** Creates or edits a goal. Linking an account makes its balance the goal's progress. */
const props = defineProps<{
    goal?: FinancialGoal | null;
    accounts: FinancialAccount[];
    currencies: Currency[];
}>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [] }>();

const toast = useToast();
const form = reactive({
    name: '',
    kind: 'savings' as FinancialGoalKind,
    currency: 'KES' as Currency,
    target_amount: '',
    target_date: '' as string | null,
    account_id: '' as number | '' | null,
});
const errors = ref<Record<string, string>>({});
const saving = ref(false);

const accountOptions = computed(() => [
    { value: '' as const, label: 'None: I’ll record what I put in' },
    ...props.accounts
        .filter((a) => a.currency === form.currency && (!a.archived || a.id === props.goal?.account?.id))
        .map((a) => ({ value: a.id, label: a.name })),
]);

watch(open, (value) => {
    if (!value) return;
    const goal = props.goal;
    form.name = goal?.name ?? '';
    form.kind = goal?.kind ?? 'savings';
    form.currency = goal?.currency ?? props.currencies[0] ?? 'KES';
    form.target_amount = goal ? toDecimal(goal.target_amount, goal.currency) : '';
    form.target_date = goal?.target_date ?? '';
    form.account_id = goal?.account?.id ?? '';
    errors.value = {};
});

watch(
    () => form.currency,
    () => {
        if (form.account_id && !accountOptions.value.some((o) => o.value === form.account_id)) form.account_id = '';
    },
);

function validate(): boolean {
    errors.value = {};
    if (!form.name.trim()) errors.value.name = 'Give the goal a name.';
    if (!isAmount(form.target_amount, form.currency)) {
        errors.value.target_amount = decimalsOf(form.currency)
            ? `Enter a target above zero with up to ${decimalsOf(form.currency)} decimal places.`
            : 'Enter a whole target above zero.';
    }
    if (form.target_date && form.target_date !== props.goal?.target_date && form.target_date < todayIso())
        errors.value.target_date = 'Pick today or a later date.';
    return !Object.keys(errors.value).length;
}

async function submit(): Promise<void> {
    if (!validate()) return;
    saving.value = true;
    const input = {
        name: form.name.trim(),
        kind: form.kind,
        currency: form.currency,
        target_amount: form.target_amount.trim(),
        target_date: form.target_date || null,
        account_id: form.account_id || null,
    };
    try {
        if (props.goal) await api.accounting.goals.update(props.goal.id, input);
        else await api.accounting.goals.create(input);
        toast.success(props.goal ? 'Goal updated' : 'Goal created');
        open.value = false;
        emit('saved');
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the goal.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="goal ? 'Edit goal' : 'New goal'"
        description="Something you are saving towards, with an amount and, if you like, a date."
        :busy="saving"
    >
        <form id="goal-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                id="goal-name"
                v-model="form.name"
                label="Name"
                placeholder="Emergency fund"
                required
                autofocus
                :maxlength="LIMITS.accounting.goalName"
                :error="errors.name"
            />
            <div class="grid gap-6 sm:grid-cols-2">
                <Select
                    id="goal-kind"
                    v-model="form.kind"
                    label="Kind"
                    :options="goalKindOptions"
                    :error="errors.kind"
                />
                <Select
                    v-if="currencies.length > 1"
                    id="goal-currency"
                    v-model="form.currency"
                    label="Currency"
                    :options="currencyOptions(currencies)"
                    :error="errors.currency"
                />
            </div>
            <div class="grid gap-6 sm:grid-cols-2">
                <Input
                    id="goal-target"
                    v-model="form.target_amount"
                    :label="`Target (${form.currency})`"
                    inputmode="decimal"
                    autocomplete="off"
                    placeholder="0.00"
                    required
                    :error="errors.target_amount"
                />
                <DatePicker
                    id="goal-date"
                    v-model="form.target_date"
                    label="Target date"
                    optional
                    :min="todayIso()"
                    :error="errors.target_date"
                />
            </div>
            <Select
                id="goal-account"
                v-model="form.account_id"
                label="Account"
                optional
                :options="accountOptions"
                :error="errors.account_id"
                hint="Pick the account you keep this money in and its balance becomes your progress."
            />
        </form>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Cancel</Button>
            <Button type="submit" form="goal-form" :loading="saving">{{
                goal ? 'Save changes' : 'Create goal'
            }}</Button>
        </template>
    </Dialog>
</template>
