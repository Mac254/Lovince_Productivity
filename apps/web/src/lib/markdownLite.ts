/**
 * A small, safe subset of Markdown for the journal preview: headings, lists (with task boxes),
 * quotes, fenced code, rules and paragraphs; inline `code` and **bold**. It produces data for the
 * template to render as text, so nothing written can become HTML.
 */
export interface Inline {
    kind: 'text' | 'code' | 'strong';
    text: string;
}

export type Block =
    | { kind: 'heading'; level: 1 | 2 | 3; inline: Inline[] }
    | { kind: 'list'; ordered: boolean; items: { checked: boolean | null; depth: number; inline: Inline[] }[] }
    | { kind: 'quote'; inline: Inline[] }
    | { kind: 'code'; lang: string; text: string }
    | { kind: 'rule' }
    | { kind: 'paragraph'; inline: Inline[] };

const LIST_ITEM = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/;
const TASK = /^\[( |x|X)\]\s+(.*)$/;

export function parseInline(text: string): Inline[] {
    const parts: Inline[] = [];
    const pattern = /`([^`]+)`|\*\*([^*]+)\*\*/g;
    let last = 0;
    for (const match of text.matchAll(pattern)) {
        if (match.index > last) parts.push({ kind: 'text', text: text.slice(last, match.index) });
        parts.push(match[1] !== undefined ? { kind: 'code', text: match[1] } : { kind: 'strong', text: match[2]! });
        last = match.index + match[0].length;
    }
    if (last < text.length) parts.push({ kind: 'text', text: text.slice(last) });
    return parts;
}

export function parseMarkdown(source: string): Block[] {
    const lines = source.replace(/\r\n?/g, '\n').split('\n');
    const blocks: Block[] = [];
    let i = 0;

    while (i < lines.length) {
        const line = lines[i]!;

        if (!line.trim()) {
            i++;
            continue;
        }

        const fence = line.match(/^\s*```(\S*)/);
        if (fence) {
            const body: string[] = [];
            i++;
            while (i < lines.length && !/^\s*```/.test(lines[i]!)) body.push(lines[i++]!);
            i++;
            blocks.push({ kind: 'code', lang: fence[1] ?? '', text: body.join('\n') });
            continue;
        }

        const heading = line.match(/^(#{1,3})\s+(.*)$/);
        if (heading) {
            blocks.push({ kind: 'heading', level: heading[1]!.length as 1 | 2 | 3, inline: parseInline(heading[2]!) });
            i++;
            continue;
        }

        if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(line)) {
            blocks.push({ kind: 'rule' });
            i++;
            continue;
        }

        if (/^\s*>/.test(line)) {
            const quoted: string[] = [];
            while (i < lines.length && /^\s*>/.test(lines[i]!)) quoted.push(lines[i++]!.replace(/^\s*>\s?/, ''));
            blocks.push({ kind: 'quote', inline: parseInline(quoted.join('\n')) });
            continue;
        }

        if (LIST_ITEM.test(line)) {
            const ordered = /^\s*\d/.test(line);
            const items: Extract<Block, { kind: 'list' }>['items'] = [];
            while (i < lines.length && LIST_ITEM.test(lines[i]!) && /^\s*\d/.test(lines[i]!) === ordered) {
                const [, indent, , rest] = lines[i]!.match(LIST_ITEM)!;
                const task = rest!.match(TASK);
                i++;
                // A bullet with nothing after it is a placeholder still to be filled in.
                if (!(task ? task[2]! : rest!).trim()) continue;
                items.push({
                    depth: Math.min(3, Math.floor(indent!.replace(/\t/g, '  ').length / 2)),
                    checked: task ? task[1] !== ' ' : null,
                    inline: parseInline(task ? task[2]! : rest!),
                });
            }
            if (items.length) blocks.push({ kind: 'list', ordered, items });
            continue;
        }

        const paragraph: string[] = [];
        while (
            i < lines.length &&
            lines[i]!.trim() &&
            !/^(#{1,3}\s|\s*```|\s*>)/.test(lines[i]!) &&
            !LIST_ITEM.test(lines[i]!)
        ) {
            paragraph.push(lines[i++]!);
        }
        blocks.push({ kind: 'paragraph', inline: parseInline(paragraph.join('\n')) });
    }

    return blocks;
}

export interface EntrySummary {
    /** The first line with content, without Markdown markers. */
    title: string;
    /** Section headings, in order. */
    sections: string[];
    /** List items written, placeholders excluded. */
    items: number;
}

/** What a list of entries shows for one: its first real line, its sections and how much it holds. */
export function summarizeEntry(source: string): EntrySummary {
    const blocks = parseMarkdown(source);
    const text = (inline: Inline[]) =>
        inline
            .map((part) => part.text)
            .join('')
            .replace(/\s+/g, ' ')
            .trim();
    const first = blocks.find((block) => block.kind !== 'heading' && block.kind !== 'rule');
    let title = '';
    if (first?.kind === 'list') title = text(first.items[0]!.inline);
    else if (first?.kind === 'code')
        title =
            first.text
                .split('\n')
                .find((line) => line.trim())
                ?.trim() ?? '';
    else if (first && 'inline' in first) title = text(first.inline);

    const sections = blocks.flatMap((block) => (block.kind === 'heading' ? [text(block.inline)] : []));
    return {
        title: title || sections[0] || '',
        sections,
        items: blocks.reduce((sum, block) => sum + (block.kind === 'list' ? block.items.length : 0), 0),
    };
}
