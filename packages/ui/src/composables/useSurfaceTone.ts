import { inject, provide, type InjectionKey } from 'vue';

/** The surface a component sits on, so text and badges pick legible roles without props. */
export type SurfaceTone = 'default' | 'highlight' | 'accent';

const key: InjectionKey<SurfaceTone> = Symbol('surface-tone');

export function provideSurfaceTone(tone: SurfaceTone): void {
    provide(key, tone);
}

export function useSurfaceTone(): SurfaceTone {
    return inject(key, 'default');
}
