import { SETTINGS_DEFAULTS } from '@focus/types';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TimerFace from '@/features/clock/TimerFace.vue';
import { builtInWallpaper, timerFaceOf, timerFaces, useClockPrefs, wallpapers } from '@/features/clock/wallpapers';
import { useSettingsStore } from '@/stores/settings';

const api = vi.hoisted(() => ({
    settings: { get: vi.fn(), update: vi.fn(), reset: vi.fn() },
}));

vi.mock('@focus/api-client', () => ({ api, isApiError: () => false }));

describe('timer faces and wallpapers', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        localStorage.clear();
        Object.values(api.settings).forEach((fn) => fn.mockReset());
        setActivePinia(createPinia());
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('offers every face and falls back to the ring for unknown values', () => {
        expect(timerFaces.map((f) => f.id)).toEqual(['ring', 'digital', 'minimal', 'flip']);
        expect(timerFaceOf('flip')).toBe('flip');
        expect(timerFaceOf('analog')).toBe('ring');
        expect(timerFaceOf('')).toBe('ring');
    });

    it('finds the new wallpapers and falls back to the first one', () => {
        expect(wallpapers.map((w) => w.id)).toEqual(
            expect.arrayContaining(['dusk', 'forest', 'ocean', 'ember', 'dots', 'dawn']),
        );
        expect(new Set(wallpapers.map((w) => w.id)).size).toBe(wallpapers.length);
        expect(builtInWallpaper('dawn')).toMatchObject({ id: 'dawn', ink: 'dark' });
        expect(builtInWallpaper('ocean').ink).toBe('light');
        expect(builtInWallpaper('custom:3')).toBe(wallpapers[0]);
    });

    it('saves the face and wallpaper to the account and keeps them after a reload', async () => {
        api.settings.update.mockImplementation(async () => ({
            values: {
                ...structuredClone(SETTINGS_DEFAULTS),
                clock: { ...SETTINGS_DEFAULTS.clock, wallpaper: 'forest' },
                timer: { ...SETTINGS_DEFAULTS.timer, face: 'digital' },
            },
        }));
        useSettingsStore().hydrate(structuredClone(SETTINGS_DEFAULTS));
        const prefs = useClockPrefs();
        expect(prefs.face).toBe('ring');

        prefs.face = 'digital';
        prefs.wallpaper = 'forest';
        await vi.advanceTimersByTimeAsync(400);

        expect(api.settings.update).toHaveBeenCalledWith({
            timer: { face: 'digital' },
            clock: { wallpaper: 'forest' },
        });

        setActivePinia(createPinia());
        const reloaded = useClockPrefs();
        expect(reloaded.face).toBe('digital');
        expect(reloaded.wallpaper).toBe('forest');
    });

    it.each(timerFaces.map((f) => f.id))('renders the %s face with its label', (face) => {
        const wrapper = mount(TimerFace, {
            props: { face, progress: 0.5, display: '12:34', status: 'Focusing', task: 'Write report', size: 300 },
        });

        expect(wrapper.find(`[data-face="${face}"]`).exists()).toBe(true);
        expect(wrapper.text()).toContain('Write report');
        expect(wrapper.html()).toContain('Focusing, 12:34 remaining');
        expect(wrapper.findAll('.flip-card')).toHaveLength(face === 'flip' ? 4 : 0);
    });
});
