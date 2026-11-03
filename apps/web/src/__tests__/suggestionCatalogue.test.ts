import type { Suggestion } from '@focus/types';
import { describe, expect, it } from 'vitest';
import { filterSuggestions, mergeSuggestions, sortSuggestions } from '@/features/suggestions/catalogue';

const suggestion = (id: number, overrides: Partial<Suggestion> = {}) =>
    ({
        id,
        title: `Idea ${id}`,
        body: null,
        category: 'fun',
        source: 'library',
        status: 'new',
        created_at: `2026-11-0${id}T09:00:00Z`,
        ...overrides,
    }) as Suggestion;

const all = { status: 'all', category: '', source: '', query: '' } as const;

describe('suggestion catalogue', () => {
    const list = [
        suggestion(1, { title: 'Call a friend', category: 'social', status: 'done' }),
        suggestion(2, { title: 'Lunch walk', category: 'health', source: 'custom', body: 'Outside for a while' }),
        suggestion(3, { title: 'Bake bread', status: 'dismissed', source: 'insight' }),
    ];

    it('filters by status, category, source and search', () => {
        expect(filterSuggestions(list, { ...all, status: 'new' }).map((s) => s.id)).toEqual([2]);
        expect(filterSuggestions(list, { ...all, category: 'social' }).map((s) => s.id)).toEqual([1]);
        expect(filterSuggestions(list, { ...all, source: 'insight' }).map((s) => s.id)).toEqual([3]);
        expect(filterSuggestions(list, { ...all, query: 'OUTSIDE' }).map((s) => s.id)).toEqual([2]);
    });

    it('sorts by any column in either direction', () => {
        expect(sortSuggestions(list, { key: 'title', direction: 'asc' }).map((s) => s.id)).toEqual([3, 1, 2]);
        expect(sortSuggestions(list, { key: 'created', direction: 'desc' }).map((s) => s.id)).toEqual([3, 2, 1]);
        expect(sortSuggestions(list, { key: 'status', direction: 'asc' }).map((s) => s.id)).toEqual([2, 1, 3]);
    });

    it('keeps the current order and puts arrivals first when merging', () => {
        const incoming = [suggestion(4), list[2]!, { ...list[0]!, status: 'new' as const }];
        const merged = mergeSuggestions(list, incoming);
        expect(merged.list.map((s) => s.id)).toEqual([4, 1, 3]);
        expect(merged.list[1]!.status).toBe('new');
        expect(merged.added).toEqual([4]);
        expect(mergeSuggestions([], incoming).added).toEqual([]);
    });
});
