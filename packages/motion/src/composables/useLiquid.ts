import { liquid } from '@lovince/motion/primitives/liquid';
import { onBeforeUnmount, onMounted, type Ref } from 'vue';
import { prefersReducedMotion } from '../presets/motion';

export function useLiquid(root: Ref<HTMLElement | null>): void {
    let stop: (() => void) | null = null;

    onMounted(() => {
        if (root.value) stop = liquid(root.value, prefersReducedMotion() ? { duration: 0 } : {});
    });

    onBeforeUnmount(() => stop?.());
}
