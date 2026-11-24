import { describe, expect, it } from 'vitest';
import { linkHandle } from '@/features/books/handle';

describe('linkHandle', () => {
    it('shows a channel as its handle', () => {
        expect(linkHandle('https://www.youtube.com/@NetworkChuck')).toBe('@NetworkChuck');
        expect(linkHandle('https://www.youtube.com/@oratorwalubengo/videos')).toBe('@oratorwalubengo');
    });

    it('falls back to the site, or nothing', () => {
        expect(linkHandle('https://www.goodreads.com/book/show/1')).toBe('goodreads.com');
        expect(linkHandle('not a link')).toBeNull();
        expect(linkHandle(null)).toBeNull();
    });
});
