<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import type { AccountingCategory, Budget, Currency } from '@focus/types';
import { currencyOptions, decimalsOf, isAmount, toDecimal } from './money';

/** Sets or changes a category's monthly limit. Only the amount of an existing budget changes. */
const props = defineProps<{
    budget?: Budget | null;
    budgets: Budget[];
    categories: AccountingCategory[];
    currencies: Currency[];
}>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [] }>();

const toast = useToast();
const form = reactive({ category_id: null as number | null, currency: 'KES' as Currency, amount: '' });
const errors = ref<Record<string, string>>({});
const saving = ref(false);

const categoryOptions = computed(() =>
    props.categories
        .filter((c) => c.kind === 'expense' && !c.archived)
        .filter((c) => !props.budgets.some((b) => b.category.id === c.id && b.currency === form.currency))
        .map((c) => ({ value: c.id, label: c.name })),
);

watch(open, (value) => {
    if (!value) return;
    const budget = props.budget;
    form.currency = budget?.currency ?? props.currencies[0] ?? 'KES';
    form.category_id = budget?.category.id ?? categoryOptions.value[0]?.value ?? null;
    form.amount = budget ? toDecimal(budget.amount, budget.currency) : '';
    errors.value = {};
});

async function submit(): Promise<void> {
    errors.value = {};
    if (!props.budget && !form.category_id) errors.value.category_id = 'Choose a spending category.';
    if (!isAmount(form.amount, form.currency)) {
        errors.value.amount = decimalsOf(form.currency)
            ? `Enter an amount above zero with up to ${decimalsOf(form.currency)} decimal places.`
            : 'Enter a whole amount above zero.';
    }
    if (Object.keys(errors.value).length) return;

    saving.value = true;
    try {
        if (props.budget) await api.accounting.budgets.update(props.budget.id, { amount: form.amount.trim() });
        else
            await api.accounting.budgets.create({
                category_id: form.category_id!,
                currency: form.currency,
                amount: form.amount.trim(),
            });
        toast.success(props.budget ? 'Budget updated' : 'Budget set');
        open.value = false;
        emit('saved');
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the budget.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="budget ? `${budget.category.name} budget` : 'New budget'"
        description="A limit for one spending category each month. Refunds count back against it."
        :busy="saving"
    >
        <form id="budget-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <div v-if="!budget" class="grid gap-6 sm:grid-cols-2">
                <Select
                    id="budget-category"
                    v-model="form.category_id"
                    label="Category"
                    :options="categoryOptions"
                    :error="errors.category_id"
                    :hint="categoryOptions.length ? undefined : 'Every spending category already has a budget.'"
                />
                <Select
                    v-if="currencies.length > 1"
                    id="budget-currency"
                    v-model="form.currency"
                    label="Currency"
                    :options="currencyOptions(currencies)"
                    :error="errors.currency"
                />
            </div>
            <Input
                id="budget-amount"
                v-model="form.amount"
                :label="`Monthly limit (${form.currency})`"
                inputmode="decimal"
                autocomplete="off"
                placeholder="0.00"
                required
                autofocus
                :error="errors.amount"
            />
        </form>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Cancel</Button>
            <Button type="submit" form="budget-form" :loading="saving">{{
                budget ? 'Save changes' : 'Set budget'
            }}</Button>
        </template>
    </Dialog>
</template>
