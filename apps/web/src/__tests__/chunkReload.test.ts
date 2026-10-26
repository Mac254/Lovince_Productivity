import { afterEach, describe, expect, it, vi } from 'vitest';
import { clearReloadGuard, isChunkLoadError, reloadOnce } from '@/lib/chunkReload';

describe('chunk reload guard', () => {
    afterEach(() => {
        clearReloadGuard();
        vi.restoreAllMocks();
    });

    it('recognises stale-chunk errors from each browser', () => {
        expect(isChunkLoadError(new TypeError('Failed to fetch dynamically imported module: http://x/a.js'))).toBe(
            true,
        );
        expect(isChunkLoadError(new TypeError('Importing a module script failed.'))).toBe(true);
        expect(isChunkLoadError(new TypeError('error loading dynamically imported module'))).toBe(true);
        expect(isChunkLoadError(new Error('Something else'))).toBe(false);
    });

    it('reloads a path once and then refuses, so a broken build cannot loop', () => {
        const assign = vi.fn();
        vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, assign } as Location);

        expect(reloadOnce('/tasks')).toBe(true);
        expect(reloadOnce('/tasks')).toBe(false);
        expect(assign).toHaveBeenCalledOnce();

        clearReloadGuard();
        expect(reloadOnce('/tasks')).toBe(true);
    });
});
