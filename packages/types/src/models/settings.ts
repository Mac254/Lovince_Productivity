import type { Settings } from '../generated/contract';

/** One setting as `GET /settings` describes it, for building the Settings page. */
export interface SettingDescription {
    key: string;
    type: 'boolean' | 'choice';
    label: string;
    description?: string;
    options?: { value: string; label: string }[];
    default: boolean | string;
    value: boolean | string;
    /** False for settings changed elsewhere in the app, such as the clock wallpaper. */
    listed: boolean;
}

export interface SettingsModuleDescription {
    key: string;
    label: string;
    description?: string;
    settings: SettingDescription[];
}

/** `GET /settings`: every module joined, and the values the app applies. */
export interface SettingsState {
    modules: SettingsModuleDescription[];
    values: Settings;
}

/** A partial update: only the settings that change. */
export type SettingsChanges = { [M in keyof Settings]?: Partial<Settings[M]> };
