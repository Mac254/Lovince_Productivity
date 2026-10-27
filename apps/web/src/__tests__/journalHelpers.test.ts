import { describe, expect, it } from 'vitest';
import { parseInline, parseMarkdown } from '@/lib/markdownLite';
import { standupDraft } from '@/lib/standup';
import { caretPosition, continueList, indentList } from '@/lib/textEditing';

describe('markdown preview', () => {
    it('parses headings, lists, task boxes, quotes, code and paragraphs', () => {
        const blocks = parseMarkdown(
            [
                '## Done',
                '- shipped `api`',
                '  - [x] tests',
                '1. first',
                '> note',
                '```ts',
                'const a = 1;',
                '```',
                'Plain **bold** text',
            ].join('\n'),
        );

        expect(blocks.map((b) => b.kind)).toEqual(['heading', 'list', 'list', 'quote', 'code', 'paragraph']);
        expect(blocks[1]).toMatchObject({
            ordered: false,
            items: [
                {
                    depth: 0,
                    checked: null,
                    inline: [
                        { kind: 'text', text: 'shipped ' },
                        { kind: 'code', text: 'api' },
                    ],
                },
                { depth: 1, checked: true, inline: [{ kind: 'text', text: 'tests' }] },
            ],
        });
        expect(blocks[2]).toMatchObject({ ordered: true });
        expect(blocks[4]).toEqual({ kind: 'code', lang: 'ts', text: 'const a = 1;' });
    });

    it('leaves out empty placeholder bullets', () => {
        expect(parseMarkdown('## Blockers\n- \n\n## Next\n- ship')).toMatchObject([
            { kind: 'heading' },
            { kind: 'heading' },
            { kind: 'list', items: [{ inline: [{ text: 'ship' }] }] },
        ]);
    });

    it('keeps markup as text', () => {
        expect(parseInline('<img src=x onerror=alert(1)>')).toEqual([
            { kind: 'text', text: '<img src=x onerror=alert(1)>' },
        ]);
    });
});

describe('list editing', () => {
    it('continues bullets, numbers and task boxes', () => {
        expect(continueList('- one', 5)).toEqual({ from: 5, to: 5, text: '\n- ', caret: 8 });
        expect(continueList('2. two', 6)?.text).toBe('\n3. ');
        expect(continueList('  - [x] done', 12)?.text).toBe('\n  - [ ] ');
        expect(continueList('plain text', 10)).toBeNull();
    });

    it('ends the list on an empty item', () => {
        expect(continueList('- one\n- ', 8)).toEqual({ from: 6, to: 8, text: '', caret: 6 });
    });

    it('nests and un-nests list items only', () => {
        expect(indentList('- one', 3, false)).toEqual({ from: 0, to: 0, text: '  ', caret: 5 });
        expect(indentList('  - one', 5, true)).toEqual({ from: 0, to: 2, text: '', caret: 3 });
        expect(indentList('plain', 2, false)).toBeNull();
    });

    it('reports line and column', () => {
        expect(caretPosition('ab\ncde', 5)).toEqual({ line: 2, column: 3 });
    });
});

describe('stand-up draft', () => {
    it('builds sections from the day and leaves blanks to fill', () => {
        const draft = standupDraft({
            date: '2026-10-27',
            completed: [{ id: 1, title: 'Ship the API' }],
            work_log: [{ id: 2, body: 'Outlined docs', minutes: 40, task: { id: 3, title: 'Docs' } }],
            blockers: [{ id: 4, description: 'Waiting on review', resolved: true, task: null }],
            still_open: [{ id: 3, title: 'Docs' }],
        });

        expect(draft).toBe(
            [
                '## Done',
                '- Completed Ship the API',
                '- Docs: Outlined docs (40m)',
                '',
                '## Learned',
                '- ',
                '',
                '## Blockers',
                '- Waiting on review — resolved',
                '',
                '## Next',
                '- Docs',
            ].join('\n'),
        );
    });
});
