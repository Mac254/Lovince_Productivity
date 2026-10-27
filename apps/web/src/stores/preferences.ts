import { defineStore } from 'pinia';
import { computed, reactive, ref, watch, watchEffect } from 'vue';

export type ThemeChoice = 'system' | 'dark' | 'light';
export type StartPage = 'dashboard' | 'today' | 'focus';
export type JournalFont = 'mono' | 'sans';

interface Preferences {
    theme: ThemeChoice;
    startPage: StartPage;
    reduceMotion: boolean;
    showQuotes: boolean;
    journalFont: JournalFont;
}

const KEY = 'focus.preferences';
const defaults: Preferences = {
    theme: 'dark',
    startPage: 'dashboard',
    reduceMotion: false,
    showQuotes: true,
    journalFont: 'mono',
};

function read(): Preferences {
    try {
        const stored = JSON.parse(localStorage.getItem(KEY) ?? 'null') as Partial<Preferences> | null;
        // The theme used to be stored on its own.
        const legacyTheme = localStorage.getItem('focus.theme') as ThemeChoice | null;
        const merged = { ...defaults, ...(legacyTheme ? { theme: legacyTheme } : {}), ...stored };
        return {
            theme: ['system', 'dark', 'light'].includes(merged.theme) ? merged.theme : defaults.theme,
            startPage: ['dashboard', 'today', 'focus'].includes(merged.startPage)
                ? merged.startPage
                : defaults.startPage,
            reduceMotion: merged.reduceMotion === true,
            showQuotes: merged.showQuotes !== false,
            journalFont: merged.journalFont === 'sans' ? 'sans' : 'mono',
        };
    } catch {
        return { ...defaults };
    }
}

function media(query: string) {
    const list = window.matchMedia?.(query);
    const matches = ref(list?.matches ?? false);
    list?.addEventListener('change', (event) => (matches.value = event.matches));
    return matches;
}

/**
 * Preferences for this device: theme, motion, start page, quotes and the journal's writing font. The theme and motion are
 * applied to `<html>` as `data-theme` and `data-motion`, so CSS and every component follow them.
 */
export const usePreferencesStore = defineStore('preferences', () => {
    const prefs = reactive(read());
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

    watch(
        prefs,
        (value) => {
            try {
                localStorage.setItem(KEY, JSON.stringify(value));
                localStorage.removeItem('focus.theme');
            } catch {
                // Choices still apply for this visit.
            }
        },
        { deep: true },
    );

    function reset(): void {
        Object.assign(prefs, defaults);
    }

    return { prefs, resolved, systemReducedMotion, reset };
});
