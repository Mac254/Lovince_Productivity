import { SETTINGS_DEFAULTS, type Settings } from '@focus/types';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { useClockPrefs } from '@/features/clock/wallpapers';
import { usePreferencesStore } from '@/stores/preferences';
import { useSettingsStore } from '@/stores/settings';

const api = vi.hoisted(() => ({
    settings: { get: vi.fn(), update: vi.fn(), reset: vi.fn() },
}));

vi.mock('@focus/api-client', () => ({
    api,
    isApiError: (error: unknown) => typeof error === 'object' && error !== null && 'kind' in error,
}));

const withChanges = (changes: Partial<{ [M in keyof Settings]: Partial<Settings[M]> }>): Settings => {
    const values = structuredClone(SETTINGS_DEFAULTS);
    for (const [module, settings] of Object.entries(changes)) Object.assign(values[module as keyof Settings], settings);
    return values;
};

const cached = () => JSON.parse(localStorage.getItem('focus.settings')!);

describe('settings', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        localStorage.clear();
        delete document.documentElement.dataset.motion;
        Object.values(api.settings).forEach((fn) => fn.mockReset());
        setActivePinia(createPinia());
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('carries over settings kept on this device before they moved to the account', () => {
        localStorage.setItem('focus.theme', 'light');
        localStorage.setItem('focus.clock', JSON.stringify({ hour12: true, tick: true }));
        localStorage.setItem('focus.timer', JSON.stringify({ sound: false }));

        const preferences = usePreferencesStore();

        expect(preferences.prefs.theme).toBe('light');
        expect(document.documentElement.dataset.theme).toBe('light');
        expect(useClockPrefs().hour12).toBe(true);
        expect(cached().unsynced).toEqual({
            appearance: { theme: 'light' },
            clock: { time_format: '12h', ticking: true },
            timer: { chime: false },
        });
        expect(localStorage.getItem('focus.theme')).toBeNull();
    });

    it('takes the account settings at sign-in and sends changes made before it', async () => {
        localStorage.setItem('focus.preferences', JSON.stringify({ startPage: 'today' }));
        api.settings.update.mockResolvedValue({ values: withChanges({ general: { start_page: 'today' }, appearance: { theme: 'light' } }) });
        const settings = useSettingsStore();

        settings.hydrate(withChanges({ appearance: { theme: 'light' } }));

        expect(settings.values.appearance.theme).toBe('light');
        expect(settings.values.general.start_page).toBe('today');
        expect(api.settings.update).toHaveBeenCalledWith({ general: { start_page: 'today' } });
        await vi.runAllTimersAsync();
        expect(cached().unsynced).toBeUndefined();
    });

    it('applies reduced motion and saves a burst of changes to the account at once', async () => {
        api.settings.update.mockImplementation(async (changes) => ({ values: withChanges(changes) }));
        useSettingsStore().hydrate(structuredClone(SETTINGS_DEFAULTS));
        const preferences = usePreferencesStore();

        preferences.prefs.reduceMotion = true;
        preferences.prefs.startPage = 'today';
        await nextTick();
        expect(document.documentElement.dataset.motion).toBe('reduce');
        expect(api.settings.update).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(400);
        expect(api.settings.update).toHaveBeenCalledOnce();
        expect(api.settings.update).toHaveBeenCalledWith({
            appearance: { reduce_motion: true },
            general: { start_page: 'today' },
        });
    });

    it('keeps a change the server could not take and sends it again', async () => {
        api.settings.update.mockRejectedValueOnce({ kind: 'network' });
        const settings = useSettingsStore();
        settings.hydrate(structuredClone(SETTINGS_DEFAULTS));

        settings.set('timer', 'auto_break', true);
        await vi.advanceTimersByTimeAsync(400);

        expect(settings.values.timer.auto_break).toBe(true);
        expect(cached().unsynced).toEqual({ timer: { auto_break: true } });
    });

    it('falls back to the account values when a change is refused', async () => {
        api.settings.update.mockRejectedValueOnce({ kind: 'validation' });
        api.settings.get.mockResolvedValue({ values: structuredClone(SETTINGS_DEFAULTS) });
        const settings = useSettingsStore();
        settings.hydrate(structuredClone(SETTINGS_DEFAULTS));

        settings.set('clock', 'wallpaper', 'custom:9');
        await vi.advanceTimersByTimeAsync(400);

        expect(settings.values.clock.wallpaper).toBe('neon');
    });

    it('does not save while signed out and resets to the defaults', async () => {
        const preferences = usePreferencesStore();
        Object.assign(preferences.prefs, { theme: 'light', reduceMotion: true, showQuotes: false, startPage: 'focus' });
        await vi.advanceTimersByTimeAsync(400);
        expect(api.settings.update).not.toHaveBeenCalled();

        await preferences.reset();
        await nextTick();

        expect({ ...preferences.prefs }).toEqual({
            theme: 'dark',
            startPage: 'dashboard',
            reduceMotion: false,
            showQuotes: true,
            journalFont: 'mono',
        });
        expect(document.documentElement.dataset.motion).toBeUndefined();
        expect(api.settings.reset).not.toHaveBeenCalled();
    });

    it('resets on the account when signed in', async () => {
        api.settings.reset.mockResolvedValue({ values: structuredClone(SETTINGS_DEFAULTS) });
        const settings = useSettingsStore();
        settings.hydrate(withChanges({ journal: { font: 'sans' } }));

        await settings.reset('journal');

        expect(api.settings.reset).toHaveBeenCalledWith('journal');
        expect(settings.values.journal.font).toBe('mono');
    });
});
