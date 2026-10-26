const KEY = 'focus.chunk-reload';

/** Browsers word this differently; all mean "a lazy page's code could not be loaded". */
export function isChunkLoadError(error: unknown): boolean {
    const message = error instanceof Error ? error.message : String(error);
    return /Failed to fetch dynamically imported module|Importing a module script failed|error loading dynamically imported module|Unable to preload CSS/i.test(
        message,
    );
}

/**
 * Reloads the page once to pick up fresh code (after a deploy or a dev-server edit).
 * Returns false if a reload for this path was already attempted, so a broken build can't loop.
 */
export function reloadOnce(path: string): boolean {
    try {
        if (sessionStorage.getItem(KEY) === path) return false;
        sessionStorage.setItem(KEY, path);
    } catch {
        // Without storage we cannot guard against loops, so don't auto-reload.
        return false;
    }
    window.location.assign(path);
    return true;
}

export function clearReloadGuard(): void {
    try {
        sessionStorage.removeItem(KEY);
    } catch {
        // Nothing to clear.
    }
}
