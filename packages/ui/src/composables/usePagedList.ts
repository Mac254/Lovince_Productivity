import { computed, reactive, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';

export function usePagedList<T>(items: MaybeRefOrGetter<T[]>, perPage = 10) {
    const page = ref(1);
    const lastPage = computed(() => Math.max(1, Math.ceil(toValue(items).length / perPage)));
    const visible = computed(() => toValue(items).slice((page.value - 1) * perPage, page.value * perPage));

    watch(lastPage, (last) => {
        if (page.value > last) page.value = last;
    });

    return reactive({ page, lastPage, visible });
}
