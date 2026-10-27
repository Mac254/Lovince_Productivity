/** One replacement in a text field: swap `from`–`to` for `text`, then place the caret at `caret`. */
export interface Edit {
    from: number;
    to: number;
    text: string;
    caret: number;
}

const LIST_LINE = /^(\s*)([-*+]|(\d+)([.)]))\s+(\[[ xX]\]\s+)?/;

function lineAt(value: string, position: number): { start: number; text: string } {
    const start = value.lastIndexOf('\n', position - 1) + 1;
    const end = value.indexOf('\n', position);
    return { start, text: value.slice(start, end === -1 ? value.length : end) };
}

/**
 * Enter on a list line: start the next item (numbers count up, task boxes come back unticked).
 * Enter on an empty item ends the list instead. Returns null for any other line.
 */
export function continueList(value: string, position: number): Edit | null {
    const { start, text } = lineAt(value, position);
    const match = text.match(LIST_LINE);
    if (!match || position < start + match[0].length) return null;

    if (!text.slice(match[0].length).trim()) return { from: start, to: start + text.length, text: '', caret: start };

    const [, indent, bullet, number, delimiter, task] = match;
    const next = `${indent}${number ? `${Number(number) + 1}${delimiter}` : bullet} ${task ? '[ ] ' : ''}`;
    return { from: position, to: position, text: `\n${next}`, caret: position + next.length + 1 };
}

/** Tab / Shift+Tab on a list line nests or un-nests it by two spaces. Returns null for other lines. */
export function indentList(value: string, position: number, outdent: boolean): Edit | null {
    const { start, text } = lineAt(value, position);
    if (!LIST_LINE.test(text)) return null;
    if (!outdent) return { from: start, to: start, text: '  ', caret: position + 2 };
    const remove = text.match(/^ {1,2}/)?.[0].length ?? 0;
    return remove ? { from: start, to: start + remove, text: '', caret: position - remove } : null;
}

/** 1-based line and column of a caret, as an editor's status bar shows them. */
export function caretPosition(value: string, position: number): { line: number; column: number } {
    const before = value.slice(0, position);
    const line = before.split('\n').length;
    return { line, column: position - before.lastIndexOf('\n') };
}
