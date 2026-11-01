import { mount } from '@vue/test-utils';
import { SETTINGS_DEFAULTS, type User } from '@focus/types';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import { useReminders } from '@/composables/useReminders';
import { useAuthStore } from '@/stores/auth';
import { useSettingsStore } from '@/stores/settings';

const api = vi.hoisted(() => ({
    tasks: { list: vi.fn() },
    timeBlocks: { list: vi.fn() },
    settings: { get: vi.fn(), update: vi.fn(), reset: vi.fn() },
}));
const info = vi.hoisted(() => vi.fn());

vi.mock('@focus/api-client', () => ({ api, isApiError: () => false }));
vi.mock('@focus/ui', async (original) => ({
    ...(await original<object>()),
    useToast: () => ({ info, success: vi.fn(), error: vi.fn() }),
}));

const Host = defineComponent({
    setup: () => useReminders(),
    template: '<div />',
});

describe('reminders', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 10, 2, 9, 3));
        localStorage.clear();
        info.mockReset();
        api.tasks.list.mockResolvedValue({ data: [{ title: 'Send invoice' }], meta: { total: 1 } });
        api.timeBlocks.list.mockResolvedValue([{ id: 7, title: 'Write', start_minute: 9 * 60 + 8, minutes: 30 }]);
        setActivePinia(createPinia());
        useAuthStore().user = { id: 1, name: 'T', email: 't@example.test' } as User;
        const settings = structuredClone(SETTINGS_DEFAULTS);
        settings.tasks.daily_reminder = '9';
        useSettingsStore().hydrate(settings);
    });

    afterEach(() => vi.useRealTimers());

    it('announces what is due today once and blocks about to start', async () => {
        const wrapper = mount(Host);
        await vi.advanceTimersByTimeAsync(0);
        expect(info).toHaveBeenCalledWith('1 task due today · Send invoice');
        expect(info).toHaveBeenCalledWith('Write · Starts in 5 min');

        await vi.advanceTimersByTimeAsync(60_000);
        expect(info).toHaveBeenCalledTimes(2);
        wrapper.unmount();
    });

    it('stays quiet when reminders are off', async () => {
        const settings = structuredClone(SETTINGS_DEFAULTS);
        settings.planner.reminder = 'off';
        useSettingsStore().hydrate(settings);
        mount(Host);
        await vi.advanceTimersByTimeAsync(0);
        expect(info).not.toHaveBeenCalled();
    });
});
