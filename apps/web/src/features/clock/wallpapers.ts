export interface Wallpaper {
    id: string;
    label: string;
    /** Whether text on it should be light or dark. */
    ink: 'light' | 'dark';
}

/** Drawn in CSS from theme colours (see FocusClockPage styles) — no external images. */
export const wallpapers: Wallpaper[] = [
    { id: 'neon', label: 'Neon glow', ink: 'light' },
    { id: 'grid', label: 'Charcoal grid', ink: 'light' },
    { id: 'aurora', label: 'Aurora', ink: 'light' },
    { id: 'tones', label: 'Job tones', ink: 'light' },
    { id: 'midnight', label: 'Midnight', ink: 'light' },
    { id: 'paper', label: 'Paper', ink: 'dark' },
];

const KEY = 'focus.clock';

/** Uploaded wallpapers are stored as `custom:<id>` in the preferences. */
export const customWallpaperKey = (id: number) => `custom:${id}`;
export const isCustomWallpaper = (value: string) => /^custom:\d+$/.test(value);

export interface ClockPrefs {
    wallpaper: string;
    hour12: boolean;
    seconds: boolean;
    tick: boolean;
}

/** Per-device preferences; storage can be unavailable, so every access is guarded. */
export const defaultClockPrefs: ClockPrefs = { wallpaper: 'neon', hour12: false, seconds: true, tick: false };

export function loadPrefs(): ClockPrefs {
    const fallback: ClockPrefs = { ...defaultClockPrefs };
    try {
        const stored = JSON.parse(localStorage.getItem(KEY) ?? 'null') as Partial<ClockPrefs> | null;
        return {
            ...fallback,
            ...stored,
            wallpaper: wallpapers.some((w) => w.id === stored?.wallpaper) ? stored!.wallpaper! : fallback.wallpaper,
        };
    } catch {
        return fallback;
    }
}

export function savePrefs(prefs: ClockPrefs): void {
    try {
        localStorage.setItem(KEY, JSON.stringify(prefs));
    } catch {
        // Preferences simply won't persist.
    }
}
