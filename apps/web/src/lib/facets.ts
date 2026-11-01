import type { Ref, WritableComputedRef } from 'vue';

/**
 * Bridges a filter whose "no filter" value is `'all'` to a FacetFilter, where it is `''`:
 * clearing the facet writes `'all'` back.
 */
export function allFacet<T extends string>(filter: Ref<T>): WritableComputedRef<Exclude<T, 'all'> | ''> {
    return computed({
        get: () => (filter.value === 'all' ? '' : (filter.value as Exclude<T, 'all'>)),
        set: (value) => (filter.value = (value || 'all') as T),
    });
}

/** The options a FacetFilter lists: everything except the `'all'` choice. */
export const withoutAll = <T extends string>(options: { value: T; label: string }[]) =>
    options.filter((option): option is { value: Exclude<T, 'all'>; label: string } => option.value !== 'all');
