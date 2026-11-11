import type { KineticEffect } from '@lovince/motion/primitives/kinetic';
import { onBeforeUnmount, onMounted, watch, type Ref } from 'vue';
import { motionConfig, prefersReducedMotion } from '../presets/motion';

export type { KineticEffect };

const loadKinetic = () => import('@lovince/motion/primitives/kinetic').catch(() => null);

/** Starts downloading the title effect while the app boots, so the first title never waits on it. */
export function preloadKinetic(): void {
    if (!prefersReducedMotion()) void loadKinetic();
}

const reveal = (el: HTMLElement | null) => el?.setAttribute('data-kinetic-ready', '');

/**
 * The effect and GSAP load on first use, so they stay off the app's first download. Until the
 * effect starts, the text stays hidden by `[data-kinetic]:not([data-kinetic-ready])` in styles.css.
 */
export function useKinetic(root: Readonly<Ref<HTMLElement | null>>, effect: () => KineticEffect | undefined): void {
    let stop: (() => void) | null = null;
    let run = 0;

    async function start(): Promise<void> {
        const current = ++run;
        stop?.();
        stop = null;
        const el = root.value;
        if (!el || !effect() || prefersReducedMotion()) return reveal(el);
        const effects = await loadKinetic();
        if (current !== run) return;
        if (effects && root.value === el) stop = effects.kinetic(el, { config: motionConfig });
        reveal(el);
    }

    onMounted(start);
    watch([() => root.value, effect], start, { flush: 'post' });
    onBeforeUnmount(() => {
        run++;
        stop?.();
    });
}
