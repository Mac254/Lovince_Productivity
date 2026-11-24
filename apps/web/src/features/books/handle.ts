/** A short, social-style name for a link: `@handle` for a channel or profile, otherwise its site. */
export function linkHandle(link: string | null | undefined): string | null {
    if (!link) return null;
    try {
        const url = new URL(link);
        const handle = /^\/(@[\w.-]+)/.exec(url.pathname)?.[1];
        return handle ?? url.hostname.replace(/^www\./, '');
    } catch {
        return null;
    }
}
