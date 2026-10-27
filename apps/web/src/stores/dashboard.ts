import { api, errorMessage } from '@focus/api-client';
import type { Dashboard } from '@focus/types';
import { defineStore } from 'pinia';
import { ref, shallowRef } from 'vue';
import { todayIso } from '@/lib/dates';

/**
 * One dashboard snapshot shared by the dashboard, today and focus views. It is fetched when a
 * view that shows it opens and the copy is missing, from an earlier day, or invalidated; changes
 * made elsewhere only mark it stale, and it refetches straight away only while it is on screen.
 */
export const useDashboardStore = defineStore('dashboard', () => {
    const data = shallowRef<Dashboard | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    let stale = false;
    let viewers = 0;
    let inflight: Promise<void> | null = null;

    /** Fetches now, sharing a request that is already running. */
    function load(): Promise<void> {
        inflight ??= (async () => {
            loading.value = true;
            error.value = null;
            try {
                data.value = await api.dashboard.get();
                stale = false;
            } catch (e) {
                error.value = errorMessage(e, 'Could not load your dashboard.');
            } finally {
                loading.value = false;
                inflight = null;
            }
        })();
        return inflight;
    }

    /** Fetches only when there is no usable copy. */
    function ensure(): Promise<void> {
        const usable = data.value && !stale && data.value.date === todayIso();
        return usable ? Promise.resolve() : load();
    }

    /** Data changed: refetch if a view is showing it, otherwise on the next visit. */
    function invalidate(): Promise<void> {
        stale = true;
        return viewers > 0 ? load() : Promise.resolve();
    }

    /** Called by views that display the snapshot; returns the matching detach. */
    function attach(): () => void {
        viewers++;
        void ensure();
        return () => {
            viewers = Math.max(0, viewers - 1);
        };
    }

    function reset(): void {
        data.value = null;
        stale = false;
    }

    return { data, loading, error, load, ensure, invalidate, attach, reset };
});
