import type { IconName } from '@focus/ui';

export type ViewMode = 'grid' | 'table';

const MODES: readonly ViewMode[] = ['grid', 'table'];

export const viewModeOptions: { value: ViewMode; label: string; icon: IconName }[] = [
    { value: 'grid', label: 'Grid view', icon: 'grid' },
    { value: 'table', label: 'Table view', icon: 'table' },
];

export function useViewMode(page: string, fallback: ViewMode = 'grid') {
    const auth = useAuthStore();
    const key = computed(() => `focus:view:${auth.user?.id ?? 'guest'}:${page}`);
    const mode = ref<ViewMode>(read(key.value) ?? fallback);

    watch(key, (next) => (mode.value = read(next) ?? fallback));
    watch(mode, (value) => {
        try {
            localStorage.setItem(key.value, value);
        } catch {
            return;
        }
    });

    return mode;
}

function read(key: string): ViewMode | null {
    try {
        const saved = localStorage.getItem(key);
        return MODES.includes(saved as ViewMode) ? (saved as ViewMode) : null;
    } catch {
        return null;
    }
}
