import { api, errorMessage } from '@focus/api-client';
import { LIMITS, type Wallpaper } from '@focus/types';
import { useToast } from '@focus/ui';
import { computed, ref } from 'vue';
import { customWallpaperKey, wallpapers, type ClockPrefs } from './wallpapers';

/** Built-in and uploaded wallpapers, and which one is active for these preferences. */
export function useWallpapers(prefs: ClockPrefs) {
    const toast = useToast();
    const uploads = ref<Wallpaper[]>([]);
    const uploading = ref(false);

    const activeUpload = computed(
        () => uploads.value.find((w) => customWallpaperKey(w.id) === prefs.wallpaper) ?? null,
    );
    const wallpaper = computed(() =>
        activeUpload.value
            ? { id: 'photo', label: activeUpload.value.name, ink: 'light' as const }
            : (wallpapers.find((w) => w.id === prefs.wallpaper) ?? wallpapers[0]!),
    );

    async function load(): Promise<void> {
        try {
            uploads.value = await api.wallpapers.list();
            // A chosen photo that was removed elsewhere falls back to the default wallpaper.
            if (prefs.wallpaper.startsWith('custom:') && !activeUpload.value) prefs.wallpaper = wallpapers[0]!.id;
        } catch {
            // Built-in wallpapers still work without the list.
        }
    }

    async function upload(file: File): Promise<void> {
        if (file.size > LIMITS.wallpaperKb * 1024) {
            toast.error(`Choose an image under ${Math.round(LIMITS.wallpaperKb / 1024)} MB.`);
            return;
        }
        uploading.value = true;
        try {
            const added = await api.wallpapers.upload(file);
            uploads.value = [added, ...uploads.value];
            prefs.wallpaper = customWallpaperKey(added.id);
            toast.success('Wallpaper added');
        } catch (e) {
            toast.error(errorMessage(e, 'Could not upload that image.'));
        } finally {
            uploading.value = false;
        }
    }

    async function removeActive(): Promise<void> {
        const current = activeUpload.value;
        if (!current) return;
        try {
            await api.wallpapers.remove(current.id);
            uploads.value = uploads.value.filter((w) => w.id !== current.id);
            prefs.wallpaper = wallpapers[0]!.id;
            toast.success('Wallpaper removed');
        } catch (e) {
            toast.error(errorMessage(e, 'Could not remove the wallpaper.'));
        }
    }

    return { uploads, uploading, activeUpload, wallpaper, load, upload, removeActive };
}
