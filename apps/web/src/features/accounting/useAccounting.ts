import { api, errorMessage } from '@focus/api-client';
import type { AccountingCategory, FinancialAccount, Transaction } from '@focus/types';
import { useToast } from '@focus/ui';
import { ref, shallowRef } from 'vue';

/** The accounts and categories the transaction form picks from, archived ones included so old records still show. */
export function useAccountingLists() {
    const accounts = shallowRef<FinancialAccount[]>([]);
    const categories = shallowRef<AccountingCategory[]>([]);

    async function load(): Promise<void> {
        [accounts.value, categories.value] = await Promise.all([
            api.accounting.accounts.list({ archived: true }),
            api.accounting.categories.list({ archived: true }),
        ]);
    }

    return { accounts, categories, load };
}

/** Voiding and reversing behind a confirmation, then `changed` so the page reloads. */
export function useTransactionActions(changed: () => unknown) {
    const toast = useToast();
    const voiding = ref<Transaction | null>(null);
    const reversing = ref<Transaction | null>(null);
    const busy = ref(false);

    async function act(work: () => Promise<unknown>, success: string, failure: string): Promise<void> {
        busy.value = true;
        try {
            await work();
            toast.success(success);
            voiding.value = null;
            reversing.value = null;
            await changed();
        } catch (e) {
            toast.error(errorMessage(e, failure));
        } finally {
            busy.value = false;
        }
    }

    const confirmVoid = () =>
        voiding.value &&
        act(() => api.accounting.transactions.void(voiding.value!.id), 'Transaction voided', 'Could not void it.');
    const confirmReverse = () =>
        reversing.value &&
        act(
            () => api.accounting.transactions.reverse(reversing.value!.id),
            'Reversal recorded',
            'Could not reverse it.',
        );

    return { voiding, reversing, busy, confirmVoid, confirmReverse };
}
