import { defineStore } from 'pinia';
import { computed, ref, watch, watchEffect } from 'vue';

export type ThemeChoice = 'system' | 'dark' | 'light';

const KEY = 'focus.theme';
const choices: ThemeChoice[] = ['system', 'dark', 'light'];

function read(): ThemeChoice {
    try {
        const stored = localStorage.getItem(KEY) as ThemeChoice | null;
        return stored && choices.includes(stored) ? stored : 'dark';
    } catch {
        return 'dark';
    }
}

/** Appearance for this device: the chosen theme, applied to the document as `data-theme`. */
export const usePreferencesStore = defineStore('preferences', () => {
    const theme = ref<ThemeChoice>(read());
    const media = window.matchMedia?.('(prefers-color-scheme: light)');
    const systemLight = ref(media?.matches ?? false);
    media?.addEventListener('change', (event) => (systemLight.value = event.matches));

    const resolved = computed<'dark' | 'light'>(() =>
        theme.value === 'system' ? (systemLight.value ? 'light' : 'dark') : theme.value,
    );

    watchEffect(() => {
        const root = document.documentElement;
        root.dataset.theme = resolved.value;
        const canvas = getComputedStyle(root).getPropertyValue('--canvas').trim();
        if (canvas) document.querySelector('meta[name="theme-color"]')?.setAttribute('content', canvas);
    });

    watch(theme, (value) => {
        try {
            localStorage.setItem(KEY, value);
        } catch {
            // The choice still applies for this visit.
        }
    });

    return { theme, resolved };
});
