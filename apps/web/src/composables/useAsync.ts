import { errorMessage, isApiError } from '@focus/api-client';
import { onBeforeUnmount, ref, shallowRef } from 'vue';

/**
 * Loading, data and error for one loader. Each run aborts the previous request,
 * so fast filter changes never render stale results.
 */
export function useAsync<T>(loader: (signal: AbortSignal) => Promise<T>) {
    const data = shallowRef<T | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    let controller: AbortController | null = null;

    async function run(): Promise<void> {
        controller?.abort();
        const current = (controller = new AbortController());
        loading.value = true;
        error.value = null;
        try {
            data.value = await loader(current.signal);
        } catch (e) {
            if (isApiError(e) && e.isCancelled) return;
            error.value = errorMessage(e);
        } finally {
            if (controller === current) loading.value = false;
        }
    }

    onBeforeUnmount(() => controller?.abort());

    return { data, loading, error, run };
}
