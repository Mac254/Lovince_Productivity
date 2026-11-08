import type { Book } from '@focus/types';

export function chapterSummary(book: Pick<Book, 'chapters_read' | 'chapters_total' | 'status'>): string {
    const { chapters_read: read, chapters_total: total } = book;
    if (total) return `${read} of ${total} chapters`;
    if (read) return `${read} ${read === 1 ? 'chapter' : 'chapters'} read`;
    return book.status === 'finished' ? 'Finished' : 'Not started';
}

export function toCount(text: string | null | undefined): number | null {
    const trimmed = (text ?? '').trim();
    if (!trimmed) return null;
    return /^\d+$/.test(trimmed) ? Number(trimmed) : Number.NaN;
}
