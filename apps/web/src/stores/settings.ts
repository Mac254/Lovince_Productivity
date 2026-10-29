import { api, isApiError } from '@focus/api-client';
import { SETTINGS_DEFAULTS, type Settings, type SettingsChanges, type SettingsModuleKey } from '@focus/types';
import { defineStore } from 'pinia';
import { computed, reactive, type WritableComputedRef } from 'vue';

const KEY = 'focus.settings';
const SAVE_DELAY_MS = 400;

type Value = boolean | string;
type Bag = Record<string, Record<string, Value>>;

interface Cached {
    values: Settings;
    /** Changes the server hasn't confirmed yet: made while signed out, offline, or before the page closed. */
    unsynced?: SettingsChanges;
}

const defaults = (): Settings => structuredClone(SETTINGS_DEFAULTS);

/** Copies only settings the app knows, with the type their default has; anything missing is left alone. */
function assign(target: Settings, source: unknown): void {
    const from = (source ?? {}) as Bag;
    const to = target as unknown as Bag;
    for (const [module, settings] of Object.entries(to)) {
        for (const [key, fallback] of Object.entries(settings)) {
            const value = from[module]?.[key];
            if (typeof value === typeof fallback) settings[key] = value!;
        }
    }
}

function mergeChanges(into: SettingsChanges, changes: SettingsChanges): SettingsChanges {
    const merged = structuredClone(into) as Bag;
    for (const [module, settings] of Object.entries(changes as Bag)) {
        merged[module] = { ...merged[module], ...settings };
    }
    return merged as SettingsChanges;
}

const isEmpty = (changes: SettingsChanges) => Object.keys(changes).length === 0;

function storage<T>(read: () => T, fallback: T): T {
    try {
        return read();
    } catch {
        return fallback;
    }
}

/** Settings kept per device before they moved to the account, so they carry over on the next sign-in. */
function legacyChanges(): SettingsChanges {
    const json = (key: string) => JSON.parse(localStorage.getItem(key) ?? 'null') as Record<string, unknown> | null;
    const prefs = storage(() => json('focus.preferences'), null);
    const clock = storage(() => json('focus.clock'), null);
    const timer = storage(() => json('focus.timer'), null);
    const theme = storage(() => localStorage.getItem('focus.theme'), null);

    const changes: Bag = {};
    const put = (module: string, key: string, value: unknown) => {
        if (typeof value === 'boolean' || typeof value === 'string') (changes[module] ??= {})[key] = value;
    };
    put('appearance', 'theme', prefs?.theme ?? theme);
    put('appearance', 'reduce_motion', prefs?.reduceMotion);
    put('general', 'start_page', prefs?.startPage);
    put('general', 'show_quotes', prefs?.showQuotes);
    put('journal', 'font', prefs?.journalFont);
    put('clock', 'wallpaper', clock?.wallpaper);
    if (typeof clock?.hour12 === 'boolean') put('clock', 'time_format', clock.hour12 ? '12h' : '24h');
    put('clock', 'show_seconds', clock?.seconds);
    put('clock', 'ticking', clock?.tick);
    put('timer', 'chime', timer?.sound);
    put('timer', 'auto_break', timer?.autoBreak);

    storage(() => ['focus.preferences', 'focus.clock', 'focus.theme'].forEach((key) => localStorage.removeItem(key)), undefined);
    return changes as SettingsChanges;
}

function load(): Cached {
    const cached = storage(() => JSON.parse(localStorage.getItem(KEY) ?? 'null') as Partial<Cached> | null, null);
    const values = defaults();
    assign(values, cached?.values);
    const unsynced = mergeChanges(cached?.unsynced ?? {}, cached ? {} : legacyChanges());
    assign(values, unsynced);
    return { values, unsynced };
}

/**
 * The account's settings, joined from every module the API declares. They are cached on the device so the theme and
 * start page apply before the first request, and changes are saved to the account shortly after they're made.
 */
export const useSettingsStore = defineStore('settings', () => {
    const cached = load();
    const values = reactive(cached.values);
    let pending: SettingsChanges = cached.unsynced ?? {};
    let signedIn = false;
    let timer: number | undefined;

    function persist(): void {
        storage(() => localStorage.setItem(KEY, JSON.stringify({ values, ...(isEmpty(pending) ? {} : { unsynced: pending }) })), undefined);
    }

    function apply(server: Settings): void {
        assign(values, server);
        assign(values, pending);
        persist();
    }

    function schedule(): void {
        window.clearTimeout(timer);
        if (signedIn) timer = window.setTimeout(() => void flush(), SAVE_DELAY_MS);
    }

    // Saved at once: carried-over settings have already left their old keys.
    persist();

    async function flush(): Promise<void> {
        window.clearTimeout(timer);
        if (!signedIn || isEmpty(pending)) return;
        const sending = pending;
        pending = {};
        persist();
        try {
            apply((await api.settings.update(sending)).values);
        } catch (error) {
            if (isApiError(error) && error.kind === 'validation') {
                // The server no longer accepts a value: take what it has.
                apply((await api.settings.get()).values);
                return;
            }
            pending = mergeChanges(sending, pending);
            persist();
        }
    }

    function set<M extends SettingsModuleKey, K extends keyof Settings[M]>(module: M, key: K, value: Settings[M][K]): void {
        if (values[module][key] === value) return;
        values[module][key] = value;
        pending = mergeChanges(pending, { [module]: { [key]: value } } as SettingsChanges);
        persist();
        schedule();
    }

    /** A two-way binding to one setting, for `v-model` and stores that expose it under their own name. */
    function bind<M extends SettingsModuleKey, K extends keyof Settings[M]>(
        module: M,
        key: K,
    ): WritableComputedRef<Settings[M][K]> {
        return computed({ get: () => values[module][key], set: (value) => set(module, key, value) });
    }

    /** Takes the account's settings at sign-in; changes not yet saved are kept and sent. */
    function hydrate(server: Settings): void {
        signedIn = true;
        apply(server);
        if (!isEmpty(pending)) void flush();
    }

    /** Stops saving; the cached values stay so the theme holds on the sign-in page. */
    function signOut(): void {
        signedIn = false;
        window.clearTimeout(timer);
        pending = {};
        persist();
    }

    /** Puts one module, or every setting, back to its default for the account. */
    async function reset(module?: SettingsModuleKey): Promise<void> {
        const cleared = structuredClone(pending) as Bag;
        if (module) delete cleared[module];
        pending = module ? (cleared as SettingsChanges) : {};
        if (!signedIn) {
            const fresh = defaults();
            assign(values, module ? { ...values, [module]: fresh[module] } : fresh);
            persist();
            return;
        }
        window.clearTimeout(timer);
        apply((await api.settings.reset(module)).values);
    }

    return { values, set, bind, hydrate, signOut, reset, flush };
});
