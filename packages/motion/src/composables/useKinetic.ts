import { kinetic, type KineticEffect } from '@lovince/motion/primitives/kinetic';
import { onBeforeUnmount, onMounted, watch, type Ref } from 'vue';
import { motionConfig, prefersReducedMotion } from '../presets/motion';

export type { KineticEffect };

export function useKinetic(root: Readonly<Ref<HTMLElement | null>>, effect: () => KineticEffect | undefined): void {
    let stop: (() => void) | null = null;

    function start(): void {
        stop?.();
        stop = null;
        if (!root.value || !effect() || prefersReducedMotion()) return;
        stop = kinetic(root.value, { config: motionConfig });
    }

    onMounted(start);
    watch([() => root.value, effect], start, { flush: 'post' });
    onBeforeUnmount(() => stop?.());
}
