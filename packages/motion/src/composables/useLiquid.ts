import { onBeforeUnmount, onMounted, type Ref } from 'vue';
import { prefersReducedMotion } from '../presets/motion';

/** The effect loads on first use, so it stays off the app's first download. */
export function useLiquid(root: Ref<HTMLElement | null>): void {
    let stop: (() => void) | null = null;
    let unmounted = false;

    onMounted(async () => {
        const { liquid } = await import('@lovince/motion/primitives/liquid');
        if (unmounted || !root.value) return;
        stop = liquid(root.value, prefersReducedMotion() ? { duration: 0 } : {});
    });

    onBeforeUnmount(() => {
        unmounted = true;
        stop?.();
    });
}
