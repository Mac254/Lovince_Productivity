<script setup lang="ts">
import { api, errorMessage, isApiError } from '@focus/api-client';
import { CURRENCIES, LIMITS, type AccountInput, type Currency, type FinancialAccount, type FinancialAccountType } from '@focus/types';
import { accountTypeOptions, currencyOptions, toDecimal } from './money';

const props = defineProps<{ account?: FinancialAccount | null; defaultCurrency?: Currency }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ saved: [account: FinancialAccount] }>();

const toast = useToast();
const form = reactive({
    name: '',
    type: 'bank' as FinancialAccountType,
    currency: 'KES' as Currency,
    opening_balance: '',
});
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const isEdit = computed(() => !!props.account);
const options = currencyOptions(CURRENCIES);

watch(open, (value) => {
    if (!value) return;
    const account = props.account;
    Object.assign(form, {
        name: account?.name ?? '',
        type: account?.type ?? 'bank',
        currency: account?.currency ?? props.defaultCurrency ?? 'KES',
        opening_balance: account ? toDecimal(account.opening_balance, account.currency) : '',
    });
    errors.value = {};
});

async function submit(): Promise<void> {
    errors.value = {};
    if (!form.name.trim()) errors.value.name = 'Name the account.';
    if (Object.keys(errors.value).length) return;

    saving.value = true;
    const payload: AccountInput = {
        name: form.name.trim(),
        type: form.type,
        currency: form.currency,
        opening_balance: form.opening_balance.trim() || '0',
    };
    try {
        const saved = props.account
            ? await api.accounting.accounts.update(props.account.id, payload)
            : await api.accounting.accounts.create(payload);
        toast.success(isEdit.value ? 'Account updated' : 'Account added');
        open.value = false;
        emit('saved', saved);
    } catch (e) {
        if (isApiError(e) && e.isValidation) errors.value = e.fieldErrors();
        else toast.error(errorMessage(e, 'Could not save the account.'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog
        v-model:open="open"
        :title="isEdit ? 'Edit account' : 'New account'"
        description="Anywhere you keep money: cash, a bank, M-Pesa, a SACCO or a card."
        :busy="saving"
    >
        <form id="account-form" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <Input
                id="account-name"
                v-model="form.name"
                label="Name"
                :maxlength="LIMITS.accounting.accountName"
                required
                autocomplete="off"
                :error="errors.name"
                autofocus
            />
            <div class="grid gap-6 sm:grid-cols-2">
                <Select id="account-type" v-model="form.type" label="Type" :options="accountTypeOptions" :error="errors.type" />
                <Select
                    id="account-currency"
                    v-model="form.currency"
                    label="Currency"
                    :options="options"
                    :error="errors.currency"
                />
            </div>
            <Input
                id="account-opening"
                v-model="form.opening_balance"
                label="Opening balance"
                optional
                inputmode="decimal"
                autocomplete="off"
                placeholder="0.00"
                hint="What the account held when you started tracking it. Use a minus sign for money owed."
                :error="errors.opening_balance"
            />
        </form>
        <template #footer>
            <Button variant="outline" :disabled="saving" @click="open = false">Cancel</Button>
            <Button type="submit" form="account-form" :loading="saving">{{
                isEdit ? 'Save changes' : 'Add account'
            }}</Button>
        </template>
    </Dialog>
</template>
