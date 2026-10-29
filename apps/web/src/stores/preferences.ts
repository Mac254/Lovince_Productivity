import { defineStore } from 'pinia';
import type { Settings } from '@focus/types';
import { computed, reactive, ref, watchEffect } from 'vue';
import { useSettingsStore } from './settings';

export type ThemeChoice = Settings['appearance']['theme'];
export type StartPage = Settings['general']['start_page'];
export type JournalFont = Settings['journal']['font'];

function media(query: string) {
    const list = window.matchMedia?.(query);
    const matches = ref(list?.matches ?? false);
    list?.addEventListener('change', (event) => (matches.value = event.matches));
    return matches;
}

/**
 * The app-wide settings under the names pages use: theme, motion, start page, quotes and the journal's writing font.
 * They live in the settings store and are saved to the account. The theme and motion are applied to `<html>` as
 * `data-theme` and `data-motion`, so CSS and every component follow them.
 */
export const usePreferencesStore = defineStore('preferences', () => {
    const settings = useSettingsStore();
    const prefs = reactive({
        theme: settings.bind('appearance', 'theme'),
        reduceMotion: settings.bind('appearance', 'reduce_motion'),
        startPage: settings.bind('general', 'start_page'),
        showQuotes: settings.bind('general', 'show_quotes'),
        journalFont: settings.bind('journal', 'font'),
    });
    const systemLight = media('(prefers-color-scheme: light)');
    const systemReducedMotion = media('(prefers-reduced-motion: reduce)');

    const resolved = computed<'dark' | 'light'>(() =>
        prefs.theme === 'system' ? (systemLight.value ? 'light' : 'dark') : prefs.theme,
    );

    watchEffect(() => {
        const root = document.documentElement;
        root.dataset.theme = resolved.value;
        if (prefs.reduceMotion) root.dataset.motion = 'reduce';
        else delete root.dataset.motion;
        const canvas = getComputedStyle(root).getPropertyValue('--canvas').trim();
        if (canvas) document.querySelector('meta[name="theme-color"]')?.setAttribute('content', canvas);
    });

    /** Puts every setting back to its default, on the account when signed in. */
    function reset(): Promise<void> {
        return settings.reset();
    }

    return { prefs, resolved, systemReducedMotion, reset };
});
