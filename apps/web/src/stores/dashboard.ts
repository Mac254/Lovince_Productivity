import { api, errorMessage } from '@focus/api-client';
import type { Dashboard } from '@focus/types';
import { defineStore } from 'pinia';
import { ref, shallowRef } from 'vue';

/** One dashboard snapshot shared by the day strip, dashboard and today views. */
export const useDashboardStore = defineStore('dashboard', () => {
    const data = shallowRef<Dashboard | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    let inflight: Promise<void> | null = null;

    function load(): Promise<void> {
        inflight ??= (async () => {
            loading.value = true;
            error.value = null;
            try {
                data.value = await api.dashboard.get();
            } catch (e) {
                error.value = errorMessage(e, 'Could not load your dashboard.');
            } finally {
                loading.value = false;
                inflight = null;
            }
        })();
        return inflight;
    }

    function reset(): void {
        data.value = null;
    }

    return { data, loading, error, load, reset };
});
