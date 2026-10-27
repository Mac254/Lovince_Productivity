import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import { usePreferencesStore } from '@/stores/preferences';

describe('preferences', () => {
    beforeEach(() => {
        localStorage.clear();
        delete document.documentElement.dataset.motion;
        setActivePinia(createPinia());
    });

    it('keeps a theme chosen before preferences were grouped', () => {
        localStorage.setItem('focus.theme', 'light');
        const preferences = usePreferencesStore();
        expect(preferences.prefs.theme).toBe('light');
        expect(document.documentElement.dataset.theme).toBe('light');
    });

    it('applies reduced motion to the document and saves choices', async () => {
        const preferences = usePreferencesStore();
        preferences.prefs.reduceMotion = true;
        preferences.prefs.startPage = 'today';
        await nextTick();

        expect(document.documentElement.dataset.motion).toBe('reduce');
        expect(JSON.parse(localStorage.getItem('focus.preferences')!)).toMatchObject({
            reduceMotion: true,
            startPage: 'today',
        });
    });

    it('resets to the defaults', async () => {
        const preferences = usePreferencesStore();
        Object.assign(preferences.prefs, { theme: 'light', reduceMotion: true, showQuotes: false, startPage: 'focus' });
        preferences.reset();
        await nextTick();

        expect(preferences.prefs).toEqual({
            theme: 'dark',
            startPage: 'dashboard',
            reduceMotion: false,
            showQuotes: true,
        });
        expect(document.documentElement.dataset.motion).toBeUndefined();
    });
});
