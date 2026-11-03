import type { Settings } from '@focus/types';

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
    { id: 'dusk', label: 'Dusk', ink: 'light' },
    { id: 'forest', label: 'Forest', ink: 'light' },
    { id: 'ocean', label: 'Ocean', ink: 'light' },
    { id: 'ember', label: 'Ember', ink: 'light' },
    { id: 'dots', label: 'Dot grid', ink: 'light' },
    { id: 'dawn', label: 'Dawn', ink: 'dark' },
];

export const builtInWallpaper = (id: string) => wallpapers.find((w) => w.id === id) ?? wallpapers[0]!;

export type TimerFace = Settings['timer']['face'];

export const timerFaces: { id: TimerFace; label: string }[] = [
    { id: 'ring', label: 'Ring' },
    { id: 'digital', label: 'Digital' },
    { id: 'minimal', label: 'Minimal' },
    { id: 'flip', label: 'Flip' },
];

export const timerFaceOf = (value: string): TimerFace =>
    timerFaces.find((f) => f.id === value)?.id ?? timerFaces[0]!.id;

/** Uploaded wallpapers are stored as `custom:<id>` in the settings. */
export const customWallpaperKey = (id: number) => `custom:${id}` as const;
export const isCustomWallpaper = (value: string) => /^custom:\d+$/.test(value);

export interface ClockPrefs {
    wallpaper: Settings['clock']['wallpaper'];
    hour12: boolean;
    seconds: boolean;
    tick: boolean;
    face: TimerFace;
}

/** The clock's settings under the names the clock uses, saved to the account through the settings store. */
export function useClockPrefs(): ClockPrefs {
    const settings = useSettingsStore();
    const timeFormat = settings.bind('clock', 'time_format');
    const face = settings.bind('timer', 'face');
    return reactive({
        wallpaper: settings.bind('clock', 'wallpaper'),
        hour12: computed({
            get: () => timeFormat.value === '12h',
            set: (on: boolean) => (timeFormat.value = on ? '12h' : '24h'),
        }),
        seconds: settings.bind('clock', 'show_seconds'),
        tick: settings.bind('clock', 'ticking'),
        face: computed({
            get: () => timerFaceOf(face.value),
            set: (value: TimerFace) => (face.value = value),
        }),
    });
}
