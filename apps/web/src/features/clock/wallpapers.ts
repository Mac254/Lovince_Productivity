import type { Settings } from '@focus/types';
import { computed, reactive } from 'vue';
import { useSettingsStore } from '@/stores/settings';

export interface Wallpaper {
    id: string;
    label: string;
    /** Whether text on it should be light or dark. */
    ink: 'light' | 'dark';
}

/** Drawn in CSS from theme colours (see FocusClockPage styles) — no external images. */
export const wallpapers: (Wallpaper & { id: Exclude<Settings['clock']['wallpaper'], `custom:${number}`> })[] = [
    { id: 'neon', label: 'Neon glow', ink: 'light' },
    { id: 'grid', label: 'Charcoal grid', ink: 'light' },
    { id: 'aurora', label: 'Aurora', ink: 'light' },
    { id: 'tones', label: 'Job tones', ink: 'light' },
    { id: 'midnight', label: 'Midnight', ink: 'light' },
    { id: 'paper', label: 'Paper', ink: 'dark' },
];

/** Uploaded wallpapers are stored as `custom:<id>` in the settings. */
export const customWallpaperKey = (id: number) => `custom:${id}` as const;
export const isCustomWallpaper = (value: string) => /^custom:\d+$/.test(value);

export interface ClockPrefs {
    wallpaper: Settings['clock']['wallpaper'];
    hour12: boolean;
    seconds: boolean;
    tick: boolean;
}

/** The clock's settings under the names the clock uses, saved to the account through the settings store. */
export function useClockPrefs(): ClockPrefs {
    const settings = useSettingsStore();
    const timeFormat = settings.bind('clock', 'time_format');
    return reactive({
        wallpaper: settings.bind('clock', 'wallpaper'),
        hour12: computed({ get: () => timeFormat.value === '12h', set: (on: boolean) => (timeFormat.value = on ? '12h' : '24h') }),
        seconds: settings.bind('clock', 'show_seconds'),
        tick: settings.bind('clock', 'ticking'),
    });
}
