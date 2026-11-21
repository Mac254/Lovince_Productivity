import { describe, expect, it } from 'vitest';
import { appendNote } from '@/features/notes/notes';

describe('appendNote', () => {
    it('starts an empty page with a list item', () => {
        expect(appendNote('', '  Sleep by 10:30  ')).toBe('- Sleep by 10:30');
    });

    it('continues a list and starts a new one after other text', () => {
        expect(appendNote('## Work on\n- Sleep\n\n', 'Short versions')).toBe('## Work on\n- Sleep\n- Short versions');
        expect(appendNote('## Work on', 'Sleep')).toBe('## Work on\n\n- Sleep');
    });
});
