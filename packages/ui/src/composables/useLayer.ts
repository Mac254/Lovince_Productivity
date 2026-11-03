import { computed, inject, provide, type ComputedRef, type InjectionKey, type Ref } from 'vue';

const key: InjectionKey<Readonly<Ref<HTMLElement | null>>> = Symbol('layer');

export function provideLayer(layer: Readonly<Ref<HTMLElement | null>>): void {
    provide(key, layer);
}

export function useLayer(): ComputedRef<HTMLElement | 'body'> {
    const layer = inject(key, null);
    return computed(() => layer?.value ?? 'body');
}
