/** Adds one line to the end of the notes page as a list item, keeping a blank line before a new list. */
export function appendNote(page: string, line: string): string {
    const text = page.trimEnd();
    const item = `- ${line.trim()}`;
    if (!text) return item;
    const last = text.split('\n').at(-1)!.trim();
    return `${text}${/^([-*+]|\d+[.)])\s/.test(last) ? '\n' : '\n\n'}${item}`;
}
