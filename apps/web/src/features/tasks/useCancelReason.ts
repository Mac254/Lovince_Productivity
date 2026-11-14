import { shallowRef } from 'vue';

interface Pending {
    title: string;
    resolve: (reason: string | null | undefined) => void;
}

const pending = shallowRef<Pending | null>(null);

/**
 * Asks why a task is being skipped. Resolves to the reason (`null` when none was given), or
 * `undefined` when the person backed out and the task should stay as it is.
 */
export function askCancelReason(title: string): Promise<string | null | undefined> {
    pending.value?.resolve(undefined);
    return new Promise((resolve) => {
        pending.value = { title, resolve };
    });
}

export function useCancelReasonPrompt() {
    function settle(reason: string | null | undefined): void {
        const current = pending.value;
        pending.value = null;
        current?.resolve(reason);
    }

    return { pending, settle };
}
