import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useDashboardStore } from '@/stores/dashboard';

const get = vi.fn();
vi.mock('@focus/api-client', () => ({
    api: { dashboard: { get: () => get() } },
    errorMessage: (_: unknown, fallback: string) => fallback,
}));

const snapshot = (date: string) => ({ date }) as never;

describe('dashboard store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-28T09:00:00'));
        get.mockReset().mockResolvedValue(snapshot('2026-10-28'));
    });
    afterEach(() => vi.useRealTimers());

    it('fetches once and reuses the snapshot across views', async () => {
        const store = useDashboardStore();
        const detachDashboard = store.attach();
        await store.ensure();
        detachDashboard();
        const detachToday = store.attach();
        await store.ensure();
        detachToday();

        expect(get).toHaveBeenCalledTimes(1);
    });

    it('waits for the next visit when data changes off screen', async () => {
        const store = useDashboardStore();
        await store.ensure();

        await store.invalidate();
        expect(get).toHaveBeenCalledTimes(1);

        store.attach();
        await vi.waitFor(() => expect(get).toHaveBeenCalledTimes(2));
    });

    it('refetches straight away while a view shows it', async () => {
        const store = useDashboardStore();
        store.attach();
        await vi.waitFor(() => expect(get).toHaveBeenCalledTimes(1));

        await store.invalidate();
        expect(get).toHaveBeenCalledTimes(2);
    });

    it('treats a snapshot from an earlier day as stale', async () => {
        const store = useDashboardStore();
        get.mockResolvedValueOnce(snapshot('2026-10-27'));
        await store.ensure();
        await store.ensure();

        expect(get).toHaveBeenCalledTimes(2);
    });

    it('shares one request between simultaneous callers', async () => {
        const store = useDashboardStore();
        await Promise.all([store.ensure(), store.ensure(), store.load()]);

        expect(get).toHaveBeenCalledTimes(1);
    });
});
