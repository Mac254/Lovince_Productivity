const keys: Record<string, string> = {
    Ctrl: 'Control',
    '⌘': 'Meta',
    '←': 'ArrowLeft',
    '→': 'ArrowRight',
    '↑': 'ArrowUp',
    '↓': 'ArrowDown',
};

/** Turns a displayed shortcut such as "Ctrl+Shift+H" or "⌘K" into an `aria-keyshortcuts` value. */
export function ariaKeyshortcuts(shortcut: string | undefined): string | undefined {
    if (!shortcut) return undefined;
    return shortcut
        .replace(/^⌘(?!\+)/, '⌘+')
        .split('+')
        .map((key) => keys[key] ?? key)
        .join('+');
}
