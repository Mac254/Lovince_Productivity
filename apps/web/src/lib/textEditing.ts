/** One replacement in a text field: swap `from`–`to` for `text`, then place the caret at `caret`. */
export interface Edit {
    from: number;
    to: number;
    text: string;
    caret: number;
    anchor?: number;
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

export function wrapSelection(value: string, from: number, to: number, marker: string): Edit {
    const size = marker.length;
    const inner = value.slice(from, to);
    if (value.slice(from - size, from) === marker && value.slice(to, to + size) === marker) {
        return { from: from - size, to: to + size, text: inner, anchor: from - size, caret: to - size };
    }
    if (inner.length > size * 2 && inner.startsWith(marker) && inner.endsWith(marker)) {
        const text = inner.slice(size, -size);
        return { from, to, text, anchor: from, caret: from + text.length };
    }
    return { from, to, text: `${marker}${inner}${marker}`, anchor: from + size, caret: to + size };
}

export function toggleLinePrefix(value: string, from: number, to: number, prefix: string): Edit {
    const start = value.lastIndexOf('\n', from - 1) + 1;
    const lineEnd = value.indexOf('\n', to);
    const end = lineEnd === -1 ? value.length : lineEnd;
    const lines = value.slice(start, end).split('\n');
    const ordered = prefix === '1. ';
    const own = ordered ? /^\d+\. / : new RegExp(`^${prefix.replace(/[[\]]/g, '\\$&')}`);
    const remove = lines.every((line) => own.test(line));
    const text = lines
        .map((line, index) =>
            remove
                ? line.replace(own, '')
                : `${ordered ? `${index + 1}. ` : prefix}${line.replace(/^(#{1,3} |> |- \[[ xX]\] |[-*+] |\d+[.)] )/, '')}`,
        )
        .join('\n');
    return { from: start, to: end, text, caret: start + text.length };
}

export function insertAt(from: number, to: number, text: string): Edit {
    return { from, to, text, caret: from + text.length };
}
