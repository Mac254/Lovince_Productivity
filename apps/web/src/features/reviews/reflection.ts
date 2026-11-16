export type ReflectionBlock =
    { kind: 'paragraph'; text: string } | { kind: 'bullets'; items: string[] } | { kind: 'numbered'; items: string[] };

const BULLET = /^\s*[-*•]\s+/;
const NUMBERED = /^\s*\d+[.)]\s+/;

/**
 * Splits a reflection into paragraphs and lists, so text written as `- point` or `1. point` reads as a list.
 * Everything stays plain text: nothing is rendered as HTML.
 */
export function reflectionBlocks(text: string | null | undefined): ReflectionBlock[] {
    const blocks: ReflectionBlock[] = [];
    for (const line of (text ?? '').split('\n')) {
        if (!line.trim()) {
            blocks.push({ kind: 'paragraph', text: '' });
            continue;
        }
        const kind = BULLET.test(line) ? 'bullets' : NUMBERED.test(line) ? 'numbered' : null;
        const last = blocks.at(-1);
        if (kind) {
            const item = line.replace(kind === 'bullets' ? BULLET : NUMBERED, '').trim();
            if (last?.kind === kind) last.items.push(item);
            else blocks.push({ kind, items: [item] });
        } else if (last?.kind === 'paragraph' && last.text) {
            last.text += ` ${line.trim()}`;
        } else {
            blocks.push({ kind: 'paragraph', text: line.trim() });
        }
    }
    return blocks.filter((block) => block.kind !== 'paragraph' || block.text);
}
