export function useTabQuery<T extends string>(values: readonly T[], fallback: T) {
    const route = useRoute();
    const router = useRouter();

    return computed<T>({
        get: () => {
            const tab = route.query.tab;
            return typeof tab === 'string' && values.includes(tab as T) ? (tab as T) : fallback;
        },
        set: (tab) => void router.replace({ query: { ...route.query, tab: tab === fallback ? undefined : tab } }),
    });
}
