import type { Suggestion, SuggestionCategory, SuggestionSource, SuggestionStatus } from '@focus/types';
import { SUGGESTION_CATEGORIES, SUGGESTION_SOURCES, SUGGESTION_STATUSES } from '@focus/types';

export type SuggestionSortKey = 'title' | 'category' | 'source' | 'status' | 'created';

export interface SuggestionSort {
    key: SuggestionSortKey;
    direction: 'asc' | 'desc';
}

export interface SuggestionFilters {
    status: SuggestionStatus | 'all';
    category: SuggestionCategory | '';
    source: SuggestionSource | '';
    query: string;
}

export function filterSuggestions(suggestions: Suggestion[], filters: SuggestionFilters): Suggestion[] {
    const query = filters.query.trim().toLocaleLowerCase();

    return suggestions.filter(
        (s) =>
            (filters.status === 'all' || s.status === filters.status) &&
            (!filters.category || s.category === filters.category) &&
            (!filters.source || s.source === filters.source) &&
            (!query || `${s.title} ${s.body ?? ''}`.toLocaleLowerCase().includes(query)),
    );
}

const rank = {
    category: (s: Suggestion) => SUGGESTION_CATEGORIES.indexOf(s.category),
    source: (s: Suggestion) => SUGGESTION_SOURCES.indexOf(s.source),
    status: (s: Suggestion) => SUGGESTION_STATUSES.indexOf(s.status),
};

export function sortSuggestions(suggestions: Suggestion[], { key, direction }: SuggestionSort): Suggestion[] {
    const sign = direction === 'asc' ? 1 : -1;
    const compare = (a: Suggestion, b: Suggestion): number => {
        if (key === 'title') return a.title.localeCompare(b.title);
        if (key === 'created') return (a.created_at ?? '').localeCompare(b.created_at ?? '') || a.id - b.id;
        return rank[key](a) - rank[key](b);
    };

    return [...suggestions].sort((a, b) => compare(a, b) * sign || b.id - a.id);
}

export function mergeSuggestions(
    current: Suggestion[],
    incoming: Suggestion[],
): { list: Suggestion[]; added: number[] } {
    const known = new Set(current.map((s) => s.id));
    const byId = new Map(incoming.map((s) => [s.id, s]));
    const kept = current.filter((s) => byId.has(s.id)).map((s) => byId.get(s.id)!);
    const fresh = incoming.filter((s) => !known.has(s.id));

    return { list: [...fresh, ...kept], added: known.size ? fresh.map((s) => s.id) : [] };
}
